# Deptrac — Symfony DDD 项目的配置示例

📄 [English](README.md) · [Русский](README.ru.md)

## 这是什么

`depfile.yaml.example` — 一个 [Deptrac](https://github.com/qossmic/deptrac) 配置示例，它对约定（`docs/conventions/`）中描述的架构规则实施**确定性验证**：

```
Presentation → Application → Domain
Infrastructure → Domain（实现接口）
Integration → Application + Domain
```

### 既然有约定，为什么还需要验证

约定以文本形式描述规则。但 AI 编程助手并不总是严格遵守——即使有 AGENTS.md 和角色提示，助手仍可能意外地：

- 将 Infrastructure 的依赖注入到 Domain 中
- 从 Application 直接访问 Infrastructure，绕过抽象层
- 将 DTO 放在错误的层中
- 绕过 Integration 层创建跨模块耦合

Deptrac **确定性地**捕获此类违规——在 CI 中，每次提交时都会检查。这不是"建议"，而是硬性检查：CI 红色——代码不会进入 main 分支。

## 层级

| 层级 | 描述 |
|---|---|
| `Domain` | 实体、值对象、仓库接口 |
| `DomainVo` | 值对象（→ Domain, Enum） |
| `DomainEnum` | 领域枚举（封闭） |
| `DomainDto` | 领域 DTO（→ Domain, VO, Enum） |
| `Application` | 用例、服务 |
| `ApplicationDto` | 应用 DTO（→ DTO, Enum） |
| `ApplicationCommand` | 命令（CQRS） |
| `ApplicationQuery` | 查询（CQRS） |
| `ApplicationCommandHandler` | 命令处理器 |
| `ApplicationQueryHandler` | 查询处理器 |
| `Infrastructure` | 仓库、外部服务 |
| `InfrastructureComponent` | 基础设施组件 |
| `Integration` | 外部 API、事件 |
| `IntegrationListener` | 事件监听器 |
| `Presentation` | 控制器、控制台命令 |

## 依赖规则

- **Domain** 只了解自身（DTO、VO、Enum）
- **Application** → Domain，但不依赖 Infrastructure
- **Infrastructure** 实现 Domain 接口，不依赖 Application
- **Command/Query** — 纯 DTO，不含逻辑
- **Handler** — 用例的唯一入口点
- **Presentation** → Application（Command/Query + Handler）
- **IntegrationListener** 监听事件，分发 Command/Query

## 适配你的项目

示例使用 `Common\Module\*` 作为基础命名空间。对于你的项目：

### `paths`

指定源代码扫描目录：

```yaml
paths:
  - ./src
  - ./apps
```

### `exclude_files`

要排除的文件的正则表达式模式：

```yaml
exclude_files:
  - '#.*tests.*#'
```

### `layers`

`collectors` 中的命名空间模式遵循 `docs/conventions/` 中的约定。示例使用可选的通用前缀 `(?:[A-Za-z_]+\\)?`，匹配任何根命名空间——因此同一配置无需修改即可用于 `Common\Module\...` 和 `TaskOrchestrator\Common\Module\...`。

### `ruleset`

层级之间的依赖规则 — 除非你添加/移除层级，否则通常不需要更改。

### `Presentation` collectors

Presentation 层使用一个正则表达式，匹配任何应用级命名空间（Api、Console、Web、Blog、Docs）：

```yaml
- type: classLike
  value: ^(?:[A-Za-z_]+\\)?(?:Api|Console|Web|Blog|Docs)\\Module\\.*
```

可选前缀 `(?:[A-Za-z_]+\\)?` 同时支持 `Console\Module\...` 和 `TaskOrchestrator\Console\Module\...`。如需添加你的应用命名空间，请修改交替项。

## 自定义规则

### ServiceContractDependencyRule

一个自定义 Deptrac 事件订阅器，用于强制执行**服务契约边界**——超越了静态层级规则所能表达的范围：

1. **禁止跨模块服务调用** — `Module\Billing` 中的服务不能依赖 `Module\User` 中的服务。使用 Integration 层进行跨模块通信。
2. **服务接口只能被允许的层级引用/实现** — 例如，Domain 只能引用自己的 Domain 服务接口，而 Integration 可以引用 Domain + Application + Integration 接口。
3. **`use` 导入被忽略** — 仅检查实际依赖（参数类型、返回类型、方法调用、继承），因此从另一个模块通过 IDE 导入类不会触发误报。

各层级允许的服务接口引用：

| 层级           | 可引用 (use)                  | 可实现 (inherit)              |
|----------------|-------------------------------|-------------------------------|
| Domain         | Domain                        | Domain                        |
| Application    | Domain, Application           | Domain, Application           |
| Infrastructure | Domain, Infrastructure        | Domain, Infrastructure        |
| Integration    | Domain, Application,          | Domain, Integration           |
|                |   Integration                 |                               |

**安装**：该规则由 `prikotov/coding-standard` 作为自动加载的类提供——无需复制文件。只需在 `depfile.yaml` 中注册：

```yaml
services:
  - class: PrikotovCodingStandard\Deptrac\ServiceContractDependencyRule
    tags:
      - { name: kernel.event_subscriber }
```

完整的测试套件（28 个用例）包含在包中（`tests/Deptrac/ServiceContractDependencyRuleTest.php`）。

## 运行

直接运行：

```bash
vendor/bin/deptrac analyse
```

通过 Makefile：

```makefile
.PHONY: deptrac
deptrac:
	vendor/bin/deptrac analyse --no-progress
```

在 CI 中：

```yaml
- run: vendor/bin/deptrac analyse --no-progress
```

作为 `make check` 的一部分：

```makefile
.PHONY: check
check: install tests deptrac phpcs phpmd psalm
```
