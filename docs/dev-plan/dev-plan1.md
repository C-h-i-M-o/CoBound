# CoBound（协界）第一版开发计划

## 上下文

**项目**：协界（CoBound）— 面向高校学生的课程项目协作管理平台，解决小组作业中组队困难、任务不清、进度无监管等问题。

**当前状态**：需求文档（`docs/项目需求文档/` 下 15 份文档）已完成，零代码。现需从零搭建第一版全栈应用。

**技术栈**：Next.js (App Router) + TypeScript（严格模式，禁止 `any`）+ SQLite + Prisma + pnpm + Tailwind CSS

**约束**：首版不做实时聊天、不接入外部短信/邮件/微信、不做文件上传、响应式 Web 端（桌面 + 手机浏览器）

---

## 关键设计决策

| 决策点       | 选择                                      | 理由                                     |
| ------------ | ----------------------------------------- | ---------------------------------------- |
| 认证方案     | JWT + signed httpOnly cookie（`jose` 库） | 首版无 OAuth 需求，轻量且可控            |
| 密码哈希     | `bcryptjs`                                | 成熟稳定                                 |
| API 输入验证 | `zod`                                     | 类型安全，从 schema 自动推导 TS 类型     |
| 数据获取     | SWR                                       | 客户端缓存与重验证，与 React 生态契合    |
| UI 方案      | Tailwind CSS 手写组件                     | 首版组件数有限，避免依赖膨胀             |
| 风险引擎     | 按需计算 + 结果持久化                     | 首版数据量小，无需独立定时任务           |
| 权限模型     | 中间件粗粒度 + Handler 数据级双重校验     | 中间件做角色隔离，Handler 做数据范围校验 |
| 主键策略     | `cuid()`                                  | 避免序列 ID 暴露数据规模                 |

---

## 开发原则

**文档先行**：每个阶段在编写任何代码之前，必须先完成对应的技术文档，确认方案清晰后再进入编码实现。所有技术文档统一放在 `docs/tech/` 目录下。

---

## 开发阶段

### 阶段 0：技术文档编写

**目标**：在写一行代码之前，完成全部核心技术文档，覆盖架构、API、数据库、组件、认证、风险引擎、贡献度算法七大领域。文档审核通过后，方可进入阶段 1 编码。

**文档清单**：

| 文档                                  | 内容概要                                                                                                                                                                    |
| ------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `docs/tech/architecture.md`           | 技术选型说明、分层架构图、目录结构详解、模块职责划分、数据流路径、部署与运行方式                                                                                            |
| `docs/tech/api-design.md`             | 全局规范（URL 结构、HTTP 方法语义、统一响应格式 `{success, data, error}`、分页排序参数）、错误码表、全部 API 路由清单（含方法、路径、权限、请求/响应示例）、zod Schema 定义 |
| `docs/tech/database-schema.md`        | 完整 Prisma Schema 定义、所有模型字段/类型/约束/默认值/关系、枚举值说明、索引策略、ER 图、迁移策略（SQLite → PostgreSQL 升级路径）                                          |
| `docs/tech/component-tree.md`         | Next.js App Router 路由树（含 Route Group）、页面组件层级、共享组件清单、布局组件结构、响应式断点策略、状态管理方案（SWR Context）                                          |
| `docs/tech/auth-design.md`            | 注册/登录流程、JWT 签发与验证机制、Cookie 策略（httpOnly/signed/SameSite）、Session 生命周期、三层权限体系（角色→路由→数据）、中间件拦截逻辑、权限检查函数签名              |
| `docs/tech/risk-engine-design.md`     | 5 条规则的形式化定义（触发条件、等级判定、原因模板、关联数据）、计算流程与去重策略、触发时机表、Risk 表结构与持久化逻辑、性能考量                                           |
| `docs/tech/contribution-algorithm.md` | 公式定义与各子项计算方式、数据来源映射（Task/Notification → 指标）、计算触发时机、参考不足判定逻辑、教师调分机制（adjustedScore vs referenceScore）、可解释性输出格式       |

**可交付**：7 份技术文档全部完成，通过审核确认方案无误。形成完整的需求（`项目需求文档/`）→ 设计（`tech/`）→ 计划（`dev-plan/`）文档链。

---

### 阶段 1：基础设施

**目标**：项目可运行、数据库就绪、认证可用、角色隔离生效。

**任务清单**：

1. **项目初始化**
    - `pnpm create next-app@latest . --typescript --tailwind --eslint --app --src-dir`
    - 安装依赖：`prisma @prisma/client bcryptjs jose zod swr`
    - 安装类型：`@types/bcryptjs`
    - 配置 `tsconfig.json`（严格模式开启 `noImplicitAny`、`strictNullChecks`）

2. **数据库 Schema**（`prisma/schema.prisma`）
    - 核心模型：User, Course, Project, Team, TeamMember, TeamApplication, Task, Notification, Risk, ContributionRecord, ContributionAdjustment
    - 配置模型：SystemConfig (key-value), NotificationTemplate
    - 枚举：UserRole, ProjectStatus, TaskStatus, NotificationType, RiskLevel, ApplicationStatus
    - 首次 migrate + 生成 Prisma Client

3. **认证模块**（`src/lib/auth.ts`）
    - `register()` / `login()` / `logout()` / `getSession()`
    - JWT 签发与验证（HS256，7 天过期）
    - 密码 `bcryptjs` 哈希
    - 四个 API 路由：`/api/auth/register`, `/api/auth/login`, `/api/auth/session`, `/api/auth/logout`

4. **权限模块**（`src/lib/permissions.ts`）
    - `requireRole(role)` — 角色基础鉴权
    - `requireCourseAccess(userId, courseId)` — 课程级别数据鉴权
    - `requireProjectAccess(userId, projectId)` — 项目级别数据鉴权
    - `requireTeamLeader(userId, teamId)` — 团队负责人操作鉴权

5. **中间件**（`src/middleware.ts`）
    - 路由拦截：`/api/auth/*` 公开，`/api/admin/*` 要求 admin，其余 `/api/*` 要求登录
    - 页面路由：`/student/*`、`/teacher/*`、`/admin/*` 校验对应 role
    - 登录后按 role 跳转对应 dashboard

6. **共享基础设施**
    - `src/types/*.ts` — API 请求/响应类型、枚举与常量
    - `src/lib/prisma.ts` — Prisma Client 单例
    - `src/lib/utils.ts` — 通用工具函数
    - 通用 UI 组件第一批：Button, Card, Badge, Modal, EmptyState, ProgressBar
    - 布局框架：RootLayout (AuthProvider), TopNav, 三个端空壳 Dashboard

7. **种子数据**（`prisma/seed.ts`）
    - 1 管理员 + 1 教师 + 2 学生 + 1 示例课程
    - 运行 `npx prisma db seed`

**可交付**：可注册/登录，按角色跳转不同 Dashboard，Session 持久化，数据库表就绪。

---

### 阶段 2：学生端 — 课程浏览与组队

**覆盖需求**：FR-S-01, FR-S-02, FR-S-03 | **验收用例**：TC-01

**任务清单**：

1. **课程模块 API**
    - `GET /api/courses` — 课程列表（学生看到所有课程）
    - `GET /api/courses/[courseId]` — 课程详情
    - `GET /api/courses/[courseId]/projects` — 课程下项目列表

2. **团队模块 API**
    - `POST /api/teams` — 创建团队
    - `GET /api/teams?courseId=` — 课程下团队列表
    - `GET /api/teams/[teamId]` — 团队详情
    - `POST /api/teams/[teamId]/applications` — 申请加入
    - `GET /api/teams/[teamId]/applications` — 查看申请（仅负责人）
    - `PATCH /api/teams/[teamId]/applications/[appId]` — 审批申请

3. **组队意向 API**
    - `GET /api/team-intents?courseId=` — 浏览意向
    - `POST /api/team-intents` — 发布意向

4. **学生端页面**
    - `/student/courses` — 课程列表页
    - `/student/courses/[courseId]` — 课程详情 + 项目列表 + 组队入口
    - 组队意向广场（发布表单 + 已有意向列表）
    - 团队申请入口 + 状态追踪

5. **通知生成器基础版**（`src/lib/notifier.ts`）
    - 申请结果通知（同意/拒绝时自动发送）

6. **组件**：TeamCard, TeamApplicationForm, NotificationList

**可交付**：学生可浏览课程和团队、发布/浏览组队意向、申请加入团队、负责人审批申请。通知在审批后自动生成。

---

### 阶段 3：学生端 — 任务与通知闭环

**覆盖需求**：FR-S-04, FR-S-05, FR-S-06, FR-S-07 | **验收用例**：TC-02, TC-03, TC-04

**任务清单**：

1. **项目 + 任务 API**
    - `GET /api/projects/[projectId]` — 项目空间详情
    - `GET /api/projects/[projectId]/tasks` — 任务列表
    - `POST /api/projects/[projectId]/tasks` — 创建任务
    - `GET /api/projects/[projectId]/tasks/[taskId]` — 任务详情
    - `PUT /api/projects/[projectId]/tasks/[taskId]` — 编辑任务
    - `PATCH /api/projects/[projectId]/tasks/[taskId]/progress` — 更新进度

2. **通知 API**
    - `GET /api/notifications?type=&confirmed=` — 个人通知列表
    - `PATCH /api/notifications/[notificationId]/confirm` — 确认通知

3. **通知生成器扩展**（`src/lib/notifier.ts`）
    - 任务分配时 → 通知责任成员（type=task）
    - Deadline 临近（24h 内）→ 通知责任成员（type=deadline）

4. **学生端页面**
    - `/student/courses/[courseId]/projects/[projectId]` — **项目空间**（核心页面）
        - 项目概况卡片（名称、状态、总进度、风险摘要）
        - 双栏布局：左 = 任务看板（todo/doing/done/overdue 四列），右 = 风险与提醒
        - Tab 导航：概况 | 任务 | 成员 | 风险
    - `/student/.../tasks/[taskId]` — 任务详情页
        - 任务信息、DeadlineTimer 组件、进度条 + 更新表单
        - 确认按钮（若未确认）
    - 通知中心（通知列表 + 确认操作）

5. **组件**：TaskBoard（四列看板）, TaskCard, ProgressUpdateForm, ConfirmButton, DeadlineTimer, RiskIndicator

**可交付**：完整的 **任务分配 → 通知 → 确认 → 进度更新** 闭环。学生可查看个人待办、更新进度、确认通知。

---

### 阶段 4：风险引擎 + 教师端总览

**覆盖需求**：FR-S-08, FR-T-01, FR-T-02, FR-T-03 | **验收用例**：TC-05, TC-06

**任务清单**：

1. **风险引擎**（`src/lib/risk-engine.ts`）
    - 5 条触发规则：
        - **R1-逾期任务风险**：task due < now，按逾期天数定级（high > 3d, medium 1-3d, low < 1d）
        - **R2-临近 Deadline**：due 在 24h 内且未完成 → medium
        - **R3-未确认通知**：通知 > 48h 未确认，按时长定级
        - **R4-低进度风险**：项目进度 < 30% 且创建 > 3 天
        - **R5-团队沉默**：7 天内无任何进度更新 → low
    - 按需计算 + 结果持久化到 Risk 表，自动清理已消除的旧风险
    - 触发时机：访问项目空间、更新进度、确认通知、教师查看总览

2. **风险 API**
    - `GET /api/projects/[projectId]/risks` — 项目风险列表
    - 风险通知生成（type=risk）

3. **教师端 API**
    - `POST /api/courses` — 创建课程
    - `PUT /api/courses/[courseId]` — 编辑课程
    - `GET /api/courses/[courseId]/projects` — 课程下所有团队项目

4. **教师端页面**
    - `/teacher/dashboard` — 首页：负责课程统计、高风险团队提醒
    - `/teacher/courses` — 课程列表
    - `/teacher/courses/[courseId]` — **团队总览**（核心页面）
        - TeamOverviewTable：团队名称、进度%、风险等级、未确认数、成员数
        - 支持按风险/进度排序和筛选
        - Tab：[团队总览 | 风险预警 | 贡献度]
    - `/teacher/courses/[courseId]/teams/[teamId]` — 团队详情
    - `/teacher/courses/[courseId]/risks` — 风险预警汇总

5. **组件**：TeamOverviewTable, RiskBadge, 教师端 TopNav 变体

**可交付**：风险自动生成并在学生/教师端展示，教师可全局概览所有团队进度与风险。

---

### 阶段 5：贡献度 + 管理员后台

**覆盖需求**：FR-T-04, FR-T-05, FR-A-01 至 FR-A-04 | **验收用例**：TC-07, TC-08

**任务清单**：

1. **贡献度引擎**（`src/lib/contribution-calc.ts`）
    - 公式：`参考分 = 基础分(60) + 完成率分(20) + 响应率分(10) + 阶段分(10) - 延期扣分(上限20)`
    - 完成率 = doneTasks / totalTasks
    - 响应率 = confirmed / total，阶段分由教师手动给，延期每次扣 5 分（上限 -20）
    - 数据不足时（totalTasks == 0）标记"参考不足"
    - 触发时机：教师访问贡献度页、任务状态变更、通知确认

2. **贡献度 API**
    - `GET /api/contributions/[projectId]` — 成员贡献度列表
    - `POST /api/contributions/[projectId]/adjust` — 教师调整贡献度（含理由）
    - 调整仅写入 `adjustedScore`，不覆盖系统 `referenceScore`

3. **管理员 API**
    - `GET/POST /api/users` — 用户列表 + 创建
    - `GET/PUT/DELETE /api/users/[userId]` — 用户详情/编辑/删除
    - `GET/PUT /api/settings` — 系统配置
    - `GET/PUT /api/settings/notification-templates/[id]` — 通知模板

4. **教师端贡献度页面**
    - 成员贡献度表：姓名、完成率、延期次数、确认率、参考分、调整分
    - ContributionForm（Modal）：输入调整分 + 必填理由
    - 历史调整记录

5. **管理员后台页面**
    - `/admin/dashboard` — 数据概览
    - `/admin/users` — 用户管理（搜索、角色筛选、增删改）
    - `/admin/courses` — 课程数据管理
    - `/admin/notifications/templates` — 通知模板管理
    - `/admin/settings` — 系统配置（风险阈值等）

6. **组件**：UserTable, CourseForm, TemplateEditor, ContributionForm

**可交付**：教师可查看/调整贡献度并追溯调整理由，管理员可管理所有基础数据。

---

### 阶段 6：打磨与验收

**任务清单**：

1. 响应式适配验证（桌面 ≥ 1024px / 平板 768-1023px / 手机 < 768px）
2. `pnpm tsc --noEmit` 类型检查通过
3. `pnpm build` 构建成功
4. 全部 8 个验收用例回归测试（TC-01 至 TC-08）
5. 种子数据完善（覆盖全部验收场景）
6. README 补充本地启动指南
7. 性能检查（列表分页、API < 2s）
8. 无障碍基础检查（语义标签、颜色对比度）
9. 安全排查（环境变量不入 Git、权限隔离验证）

---

## 数据库核心模型关系

```
User ──┬── Course (teacherId)         ── Project ── Task ── Risk
       ├── Team (leaderId)            ── Course                  ├── relatedTask (Task)
       ├── TeamMember (userId)                                   └── createdByUser (User)
       ├── Task (assigneeId)
       ├── Notification (receiverId)
       ├── ContributionRecord (userId) ── ContributionAdjustment (adjustedBy → User)
       ├── Risk (createdByUserId)
       └── TeamApplication (senderId)

Course ── Project ── Team ── TeamMember ── User
                        └── TeamApplication
```

---

## 目录结构

```
CoBound/
├── src/
│   ├── app/                          # Next.js App Router
│   │   ├── layout.tsx
│   │   ├── (auth)/login/ register/
│   │   ├── (student)/dashboard/ courses/ ...
│   │   ├── (teacher)/dashboard/ courses/ ...
│   │   ├── (admin)/dashboard/ users/ courses/ ...
│   │   └── api/                      # API Route Handlers
│   │       ├── auth/ courses/ projects/ teams/
│   │       ├── team-intents/ notifications/ contributions/
│   │       └── users/ settings/
│   ├── components/                   # ui/ layout/ student/ teacher/ admin/ shared/
│   ├── lib/                          # prisma.ts auth.ts permissions.ts risk-engine.ts contribution-calc.ts notifier.ts
│   └── types/                        # api.ts enums.ts
├── prisma/                           # schema.prisma seed.ts migrations/
├── docs/
│   ├── 项目需求文档/                   # 需求文档（01-15）
│   ├── dev-plan/                     # 开发计划
│   │   └── dev-plan1.md
│   └── tech/                         # 技术设计文档
│       ├── architecture.md
│       ├── api-design.md
│       ├── database-schema.md
│       ├── component-tree.md
│       ├── auth-design.md
│       ├── risk-engine-design.md
│       └── contribution-algorithm.md
└── .env.example
```

---

## 验证方式

1. **类型检查**：`pnpm tsc --noEmit` 零错误
2. **构建检查**：`pnpm build` 成功
3. **功能验证**：逐阶段运行种子数据，手动验证 8 个验收用例：
    - TC-01 学生申请团队 → 状态追踪
    - TC-02 负责人分配任务 → 任务进入待办
    - TC-03 成员确认通知 → 记录确认时间
    - TC-04 成员更新进度 → 看板同步
    - TC-05 逾期风险生成 → 高风险提示
    - TC-06 教师查看总览 → 团队进度+风险
    - TC-07 教师调整贡献度 → 保存调整+理由
    - TC-08 管理员维护课程 → 课程可被引用
4. **安全验证**：学生无法通过 URL 修改访问非己团队数据
5. **响应式验证**：手机浏览器下核心流程可用
