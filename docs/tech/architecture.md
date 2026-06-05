# 系统架构设计

## 1. 技术选型

| 层级 | 技术 | 版本 | 选型理由 |
|------|------|------|---------|
| 全栈框架 | Next.js (App Router) | 16.x | 单一代码库完成页面与 API，Server Components 减少客户端 JS 体积，Route Handler 替代传统 API 路由 |
| 语言 | TypeScript | 5.x | 严格模式（`noImplicitAny`、`strictNullChecks`），禁止 `any`，提供完整类型安全 |
| 数据库 | SQLite | — | 零配置、文件级存储，适合课程演示与本地部署；通过 Prisma 抽象，后续可平滑迁移到 PostgreSQL |
| ORM | Prisma | 6.x | 类型安全的数据库访问，Schema 定义即文档，自动生成迁移脚本 |
| 认证 | jose + bcryptjs | — | JWT 签发/验证轻量无依赖；bcryptjs 纯 JS 实现无需编译 |
| 输入验证 | zod | 3.x | Schema 自动推导 TS 类型，在 Route Handler 入口做请求体验证 |
| 样式 | Tailwind CSS | 4.x | 原子化 CSS，响应式优先，零运行时开销 |
| 数据获取 | SWR | 2.x | 客户端缓存、自动重验证、请求去重，与 React 生态深度契合 |
| 包管理 | pnpm | — | 磁盘空间友好，严格依赖解析 |

## 2. 分层架构

```
┌─────────────────────────────────────────────┐
│                 前端层（Server + Client）      │
│  ┌─────────────┐  ┌──────────────────────┐  │
│  │ Server       │  │ Client Components    │  │
│  │ Components   │  │ (SWR → API Routes)   │  │
│  │ (SSR 直查 DB)│  │                      │  │
│  └─────────────┘  └──────────────────────┘  │
├─────────────────────────────────────────────┤
│                  API 层（Route Handler）      │
│  ┌─────────┐ ┌──────────┐ ┌────────────┐   │
│  │ zod 校验 │ │ 权限检查  │ │ 业务逻辑   │   │
│  └─────────┘ └──────────┘ └────────────┘   │
├─────────────────────────────────────────────┤
│                  业务逻辑层（lib/）            │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐    │
│  │ risk-     │ │ contrib-  │ │ notifier │    │
│  │ engine    │ │ ution-calc│ │          │    │
│  └──────────┘ └──────────┘ └──────────┘    │
├─────────────────────────────────────────────┤
│                  数据访问层（Prisma）          │
│  ┌──────────────────────────────────────┐   │
│  │ Prisma Client (单例) → SQLite / PG    │   │
│  └──────────────────────────────────────┘   │
└─────────────────────────────────────────────┘
```

### 2.1 各层职责

| 层 | 职责 | 关键约束 |
|----|------|---------|
| 前端层 | 页面渲染、交互状态管理、客户端数据获取 | Server Component 可直查 DB 做首屏 SSR；Client Component 通过 SWR 调用 API，不做直接 DB 调用 |
| API 层 | 请求校验（zod）、权限鉴权（permissions.ts）、调用业务逻辑、返回统一响应 | 每个 Handler：zod 校验 → session 鉴权 → 数据级权限 → 业务逻辑 → 响应 |
| 业务逻辑层 | 风险计算、贡献度算法、通知生成 | 纯函数设计，不依赖 HTTP 上下文，可独立单元测试 |
| 数据访问层 | Prisma Client 封装，数据库 CRUD | 通过 `src/lib/prisma.ts` 单例导出，避免 dev 模式热重载创建多实例 |

## 3. 模块职责划分

```
src/
├── app/                    # Next.js 页面路由 + API 路由
│   ├── layout.tsx          # 根布局（AuthProvider 包裹）
│   ├── (auth)/             # 认证页面组（公开）
│   ├── (student)/          # 学生端页面组（需 student role）
│   ├── (teacher)/          # 教师端页面组（需 teacher role）
│   ├── (admin)/            # 管理员页面组（需 admin role）
│   └── api/                # API Route Handler（RESTful）
├── components/             # React 组件（按使用方分目录）
│   ├── ui/                 # 通用 UI 原子组件
│   ├── layout/             # 布局组件（TopNav, Sidebar, MobileNav）
│   ├── student/            # 学生端业务组件
│   ├── teacher/            # 教师端业务组件
│   ├── admin/              # 管理员端业务组件
│   └── shared/             # 跨角色共享组件
├── lib/                    # 业务逻辑（纯 TS，不依赖 React）
│   ├── prisma.ts           # Prisma Client 单例
│   ├── auth.ts             # 认证（JWT 签发/验证、密码哈希）
│   ├── permissions.ts      # 权限检查函数
│   ├── risk-engine.ts      # 风险引擎
│   ├── contribution-calc.ts # 贡献度算法
│   └── notifier.ts         # 通知生成器
└── types/                  # 共享类型定义
    ├── api.ts              # API 请求/响应类型
    └── enums.ts            # 枚举与常量
```

## 4. 数据流路径

### 4.1 读数据（GET）

```
Client Component (useSWR)
  → fetch("/api/xxx")
    → Route Handler (zod 校验 query params)
      → permissions.checkXxx(session, resourceId)
        → prisma.xxx.findMany(...)
          → 统一响应 { success: true, data }
            → SWR 缓存 → React 渲染
```

### 4.2 写数据（POST/PUT/PATCH/DELETE）

```
Client Component (表单提交)
  → fetch("/api/xxx", { method: "POST", body })
    → Route Handler
      → zod 校验 body
      → getSession(request)
      → permissions.checkXxx(session, resourceId)
      → prisma.$transaction([...])
        → 触发副作用（notifier.create / risk-engine.refresh）
          → 统一响应 { success: true, data }
```

### 4.3 Server Component 直查（仅读操作，用于 SSR 首屏）

```
Server Component (async)
  → permissions.checkXxx(session, resourceId)
    → prisma.xxx.findMany(...)
      → 直接渲染 HTML 返回客户端
```

> **安全约束**：Server Component 直查仅用于读操作且不暴露敏感字段（如 `passwordHash`）。所有写操作必须通过 API Route Handler。

## 5. 关键设计原则

### 5.1 API 响应规范

所有 API 返回统一结构：

```typescript
// 成功
{ success: true, data: T }

// 失败
{ success: false, error: string }

// 列表（含分页）
{ success: true, data: T[], total: number, page: number, pageSize: number }
```

HTTP 状态码：200（成功）、201（创建）、400（参数错误）、401（未登录）、403（无权限）、404（不存在）、500（服务器错误）。

### 5.2 错误处理策略

- Route Handler 入口：`try/catch` 包裹，zod 校验失败返回 400，权限失败返回 403，未登录返回 401
- 业务逻辑层：抛出语义化错误（如 `NotFoundError`、`PermissionDeniedError`），由 Route Handler 统一捕获转换
- 数据库层：Prisma 异常（如唯一约束冲突）在 Handler 中捕获并返回友好错误

### 5.3 未来扩展点

- **微信小程序接入**：API 返回结构与 Web 端解耦，小程序通过 HTTP 调用同一套 API Route Handler
- **数据库迁移**：Prisma Schema 已预留 PostgreSQL provider 切换能力，仅需修改 `datasource` 配置
- **通知渠道扩展**：`notifier.ts` 预留渠道参数，后续可扩展邮件、微信模板消息等
- **文件上传**：预留 `projectId/files` 路由命名空间，首版不实现

## 6. 部署与运行

### 6.1 本地开发

```bash
pnpm install
cp .env.example .env
npx prisma migrate dev
npx prisma db seed
pnpm dev
```

### 6.2 环境变量（`.env.example`）

| 变量 | 说明 | 示例 |
|------|------|------|
| `DATABASE_URL` | SQLite 文件路径 | `file:./dev.db` |
| `JWT_SECRET` | JWT 签名密钥 | `openssl rand -base64 32` |
| `COOKIE_NAME` | Session Cookie 名称 | `cobound-session` |

### 6.3 生产部署

Next.js 支持 Vercel / Docker / Node.js 服务器部署。SQLite 文件建议挂载到持久化卷。后续迁移到 PostgreSQL 时仅需修改 `DATABASE_URL` 和 Prisma provider。
