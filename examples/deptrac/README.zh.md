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

`collectors` 中的命名空间模式使用 `Common\Module` — 查找并替换为你的基础模块命名空间。示例：

```
替换前：^Common\Module\.*\Domain\.*
替换后：^MyProject\Module\.*\Domain\.*
```

### `ruleset`

层级之间的依赖规则 — 除非你添加/移除层级，否则通常不需要更改。

### `Presentation` collectors

应用程序入口点（控制器、控制台命令） — 调整为匹配你的命名空间：

```yaml
- type: classLike
  value: ^Api\v1\Module\.*        # REST API
- type: classLike
  value: ^Console\Module\.*\Command\.*  # CLI 命令
```

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
