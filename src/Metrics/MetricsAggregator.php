<?php

declare(strict_types=1);

// phpcs:disable Generic.Files.LineLength

namespace PrikotovCodingStandard\Metrics;

use InvalidArgumentException;

/** Builds the stable quality-metrics schema from PhpCodeArcheology and Deptrac reports. */
final class MetricsAggregator
{
    /** @param array<string, mixed> $analyzer @param array<string, mixed> $deptrac @param array<string, mixed> $config @return array<string, mixed> */
    public function aggregate(array $analyzer, array $deptrac, array $config = [], ?string $commit = null): array
    {
        if (!isset($analyzer['classes'], $analyzer['functions']) || !is_array($analyzer['classes']) || !is_array($analyzer['functions'])) {
            throw new InvalidArgumentException('Analyzer JSON must contain classes and functions arrays.');
        }
        if (($deptrac['schema_version'] ?? null) !== '1.0' || !is_array($deptrac['dependencies'] ?? null)) {
            throw new InvalidArgumentException('Deptrac JSON must be a metrics-json report with schema_version 1.0.');
        }

        $methodsByClass = [];
        foreach ($analyzer['functions'] as $method) {
            if (($method['type'] ?? null) !== 'method' || !is_array($method['metrics'] ?? null)) {
                continue;
            }
            $metrics = $method['metrics'];
            $parts = explode(', ', (string) ($metrics['classInfo'] ?? ''), 2);
            if (count($parts) !== 2) {
                continue;
            }
            $class = $parts[1];
            $methodsByClass[$class][] = [
                'id' => $class . '::' . $method['name'], 'loc' => (int) ($metrics['loc'] ?? 0),
                'cc' => (int) ($metrics['cc'] ?? 1), 'cc_definition_version' => '1.0',
            ];
        }

        $classes = [];
        foreach ($analyzer['classes'] as $item) {
            if (!is_array($item['metrics'] ?? null) || !is_string($item['name'] ?? null)) {
                continue;
            }
            $m = $item['metrics'];
            $id = $item['name'];
            $file = $this->relativePath((string) ($m['filePath'] ?? ''));
            if (!str_contains($file, '/') || !str_starts_with($file, 'src/')) {
                $file = 'src/' . $file;
            }
            $module = $this->module($file, $config);
            $methods = $methodsByClass[$id] ?? [];
            usort($methods, static fn ($a, $b) => $a['id'] <=> $b['id']);
            $cc = array_column($methods, 'cc');
            $classes[$id] = [
                'id' => $id, 'kind' => $this->kind($m), 'file' => $file, 'module' => $module,
                'loc' => (int) ($m['loc'] ?? 0), 'method_count' => (int) ($m['methodCount'] ?? count($methods)),
                'property_count' => (int) ($m['propertyCount'] ?? 0), 'wmc' => array_sum($cc), 'max_cc' => $cc === [] ? 0 : max($cc),
                'lcom4' => ['components' => (int) ($m['lcom'] ?? 0), 'normalized' => null, 'method_count' => count($methods), 'definition_version' => '1.0'],
                'ca' => ['count' => 0, 'types' => []], 'ce' => ['count' => 0, 'types' => []],
                'churn' => isset($m['gitChurnCount']) ? ['commits' => (int) $m['gitChurnCount'], 'changed_lines' => null] : null,
                '_methods' => $methods,
            ];
            if (count($methods) > 1) {
                $classes[$id]['lcom4']['normalized'] = ((int) $m['lcom'] - 1) / (count($methods) - 1);
            }
        }
        ksort($classes);

        $edges = [];
        foreach ($deptrac['dependencies'] as $dependency) {
            if (!is_array($dependency)) {
                continue;
            }
            $source = $dependency['source'] ?? null;
            $target = $dependency['target'] ?? null;
            if (!isset($classes[$source], $classes[$target]) || $source === $target) {
                continue;
            }
            $edges[$source . "\0" . $target] = [$source, $target];
        }
        foreach ($edges as [$source, $target]) {
            $classes[$source]['ce']['types'][$target] = $target;
            $classes[$target]['ca']['types'][$source] = $source;
        }
        foreach ($classes as &$class) {
            $class['ca']['types'] = array_values($class['ca']['types']);
            sort($class['ca']['types']);
            $class['ca']['count'] = count($class['ca']['types']);
            $class['ce']['types'] = array_values($class['ce']['types']);
            sort($class['ce']['types']);
            $class['ce']['count'] = count($class['ce']['types']);
        }
        unset($class);

        $modules = $this->modules($classes, array_values($edges));
        $findings = $this->findings($classes, $modules, $config['thresholds'] ?? []);
        $publicClasses = array_map(static fn ($class) => array_diff_key($class, ['_methods' => true]), array_values($classes));
        $methods = array_merge(...array_values(array_map(static fn ($class) => $class['_methods'], $classes)));
        usort($methods, static fn ($a, $b) => $a['id'] <=> $b['id']);

        return [
            'schema_version' => '1.0', 'scope' => ['kind' => 'project', 'source_path' => '.'],
            'metadata' => ['generated_at' => gmdate('Y-m-d\\TH:i:s\\Z'), 'commit' => $commit, 'analyzer_version' => $analyzer['toolVersion'] ?? null, 'deptrac_schema_version' => '1.0'],
            'metrics' => ['project' => $this->project($classes, $modules, $edges), 'modules' => array_values($modules), 'classes' => $publicClasses, 'methods' => $methods],
            'findings' => $findings,
        ];
    }

    /** @param array<string, array<string,mixed>> $classes @param list<array{string,string}> $edges @return array<string, array<string,mixed>> */
    private function modules(array $classes, array $edges): array
    {
        $modules = [];
        foreach ($classes as $class) {
            $modules[$class['module']]['classes'][] = $class;
        }
        foreach ($modules as $id => &$module) {
            $items = $module['classes'];
            $ids = array_column($items, 'id');
            $files = array_unique(array_column($items, 'file'));
            $internal = $incoming = $outgoing = 0;
            $outside = [];
            foreach ($edges as [$source, $target]) {
                $a = $classes[$source]['module'];
                $b = $classes[$target]['module'];
                if ($a === $id && $b === $id) {
                    $internal++;
                } elseif ($a === $id) {
                    $outgoing++;
                    $outside[$target] = true;
                } elseif ($b === $id) {
                    $incoming++;
                }
            }
            $denominator = $internal + $outgoing;
            $module = ['id' => $id, 'class_count' => count($items), 'file_count' => count($files), 'loc' => array_sum(array_column($items, 'loc')),
                'class_loc' => $this->distribution(array_column($items, 'loc')), 'wmc' => $this->distribution(array_column($items, 'wmc')), 'max_cc' => $this->distribution(array_column($items, 'max_cc')),
                'internal_dependencies' => $internal, 'incoming_dependencies' => $incoming, 'outgoing_dependencies' => $outgoing,
                'external_dependency_share' => $denominator ? $outgoing / $denominator : null, 'cohesion' => $denominator ? $internal / $denominator : null,
                'cycles' => ['count' => 0, 'components' => []], 'external_interface_size' => 0];
        }
        unset($module);
        $components = $this->components(array_keys($modules), $edges, $classes);
        foreach ($components as $component) {
            if (count($component) > 1) {
                foreach ($component as $id) {
                                $modules[$id]['cycles']['count']++;
                                $modules[$id]['cycles']['components'][] = implode(',', $component);
                }
            }
        }
        foreach ($modules as &$module) {
            sort($module['cycles']['components']);
        } unset($module);
        foreach ($edges as [$source, $target]) {
            if ($classes[$source]['module'] !== $classes[$target]['module']) {
                $modules[$classes[$target]['module']]['_interface'][$target] = true;
            }
        }
        foreach ($modules as &$module) {
            $module['external_interface_size'] = count($module['_interface'] ?? []);
            unset($module['_interface']);
        } unset($module);
        ksort($modules);
        return $modules;
    }

    /** @param list<int|float> $values @return array<string,int|float|null> */
    private function distribution(array $values): array
    {
        sort($values);
        return ['median' => $this->percentile($values, .5), 'max' => $values === [] ? null : max($values), 'p90' => $this->percentile($values, .9), 'p95' => $this->percentile($values, .95)];
    }
    /** @param list<int|float> $values */ private function percentile(array $values, float $p): int|float|null
    {
        $n = count($values);
        if (!$n) {
            return null;
        } $i = ($n - 1) * $p;
        $a = (int) floor($i);
        $b = (int) ceil($i);
        return $values[$a] + ($values[$b] - $values[$a]) * ($i - $a);
    }
    /** @param array<string,array<string,mixed>> $classes @param array<string,array<string,mixed>> $modules @param array<string,array{string,string}> $edges @return array<string,mixed> */
    private function project(array $classes, array $modules, array $edges): array
    {
        $cycles = [];
        foreach ($modules as $m) {
            foreach ($m['cycles']['components'] as $c) {
                $cycles[$c] = true;
            }
        } return ['class_count' => count($classes),'file_count' => count(array_unique(array_column($classes, 'file'))),'loc' => array_sum(array_column($classes, 'loc')),'module_count' => count($modules),'class_loc' => $this->distribution(array_column($classes, 'loc')),'wmc' => $this->distribution(array_column($classes, 'wmc')),'max_cc' => $this->distribution(array_column($classes, 'max_cc')),'inter_module_dependencies' => count(array_filter($edges, fn($e)=>$classes[$e[0]]['module'] !== $classes[$e[1]]['module'])),'cycles' => ['count' => count($cycles),'components' => array_keys($cycles)]];
    }
    /** @param list<string> $nodes @param list<array{string,string}> $edges @param array<string,array<string,mixed>> $classes @return list<list<string>> */
    private function components(array $nodes, array $edges, array $classes): array
    {
        $adj = array_fill_keys($nodes, []);
        foreach ($edges as [$a,$b]) {
            $a = $classes[$a]['module'];
            $b = $classes[$b]['module'];
            if ($a !== $b) {
                $adj[$a][$b] = true;
            }
        } $seen = [];
        $order = [];
        $visit = function ($n) use (&$visit, &$seen, &$order, $adj) {
            if (isset($seen[$n])) {
                return;
            }$seen[$n] = true;
            foreach (array_keys($adj[$n]) as $v) {
                $visit($v);
            }$order[] = $n;
        };
        foreach ($nodes as $n) {
            $visit($n);
        }$reverse = array_fill_keys($nodes, []);
        foreach ($adj as $a => $targets) {
            foreach (array_keys($targets) as $b) {
                $reverse[$b][] = $a;
            }
        }$seen = [];
        $out = [];
        while ($order) {
            $n = array_pop($order);
            if (isset($seen[$n])) {
                continue;
            }$part = [];
            $walk = function ($v) use (&$walk, &$seen, &$part, $reverse) {
                if (isset($seen[$v])) {
                    return;
                }$seen[$v] = true;
                $part[] = $v;
                foreach ($reverse[$v] as $x) {
                    $walk($x);
                }
            };
            $walk($n);
            sort($part);
            $out[] = $part;
        }return $out;
    }
    /** @param array<string,array<string,mixed>> $classes @param array<string,array<string,mixed>> $modules @param array<string,mixed> $thresholds @return list<array<string,mixed>> */
    private function findings(array $classes, array $modules, array $thresholds): array
    {
        $out = [];
        foreach ($classes as $c) {
            foreach (['loc','wmc','max_cc'] as $k) {
                if (isset($thresholds['class'][$k]) && $c[$k] > $thresholds['class'][$k]) {
                    $out[] = ['rule_id' => 'class.' . $k,'rule_version' => '1.0','subject' => ['kind' => 'class','id' => $c['id']],'values' => ['actual' => $c[$k],'threshold' => $thresholds['class'][$k]],'explanation' => 'Class metric exceeds configured threshold.'];
                }
            }
        } foreach ($modules as $m) {
            if (($m['cycles']['count'] ?? 0) > ($thresholds['module']['cycles'] ?? PHP_INT_MAX)) {
                    $out[] = ['rule_id' => 'module.cycles','rule_version' => '1.0','subject' => ['kind' => 'module','id' => $m['id']],'values' => ['actual' => $m['cycles']['count'],'threshold' => $thresholds['module']['cycles']],'explanation' => 'Module participates in a dependency cycle.'];
            }
        } usort($out, fn($a, $b)=>$a['rule_id'] . $a['subject']['id'] <=> $b['rule_id'] . $b['subject']['id']);
        return $out;
    }
    /** @param array<string,mixed> $m */ private function kind(array $m): string
    {
        return ($m['interface'] ?? false) ? 'interface' : (($m['trait'] ?? false) ? 'trait' : (($m['enum'] ?? false) ? 'enum' : 'class'));
    }
    /** @param array<string,mixed> $config */ private function module(string $file, array $config): string
    {
        if (preg_match('#^src/([^/]+)#', $file, $match)) {
            return $match[1];
        } return 'Unassigned';
    }
    private function relativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', (string)getcwd()) . '/';
        return str_starts_with($path, $root) ? substr($path, strlen($root)) : ltrim($path, '/');
    }
}
