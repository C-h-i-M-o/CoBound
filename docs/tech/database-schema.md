# 数据库 Schema 详细设计

## 1. 枚举定义

```prisma
enum UserRole {
  student   // 学生
  teacher   // 教师
  admin     // 管理员
}

enum ProjectStatus {
  preparing  // 准备组队中
  active     // 进行中
  archived   // 已归档
}

enum TaskStatus {
  todo       // 待开始
  doing      // 进行中
  done       // 已完成
  overdue    // 已逾期（由风险引擎定时标记）
}

enum NotificationType {
  task       // 任务分配通知
  deadline   // 截止时间提醒
  risk       // 风险预警通知
  system     // 系统通知
}

enum RiskLevel {
  low        // 低风险
  medium     // 中风险
  high       // 高风险
}

enum ApplicationStatus {
  pending    // 待处理
  accepted   // 已通过
  rejected   // 已拒绝
}
```

## 2. 核心模型

### 2.1 User（用户）

```prisma
model User {
  id           String    @id @default(cuid())
  name         String
  role         UserRole
  email        String    @unique
  passwordHash String
  createdAt    DateTime  @default(now())
  updatedAt    DateTime  @updatedAt

  // 关系
  teachingCourses           Course[]                  @relation("CourseTeacher")
  ledTeams                  Team[]                    @relation("TeamLeader")
  memberships               TeamMember[]
  assignedTasks             Task[]                    @relation("TaskAssignee")
  receivedNotifications     Notification[]            @relation("NotificationReceiver")
  contributions             ContributionRecord[]
  createdRisks              Risk[]                    @relation("RiskCreatedBy")
  sentApplications          TeamApplication[]         @relation("ApplicationSender")
  adjustments               ContributionAdjustment[]

  @@index([email])
  @@index([role])
}
```

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | String | PK, cuid() | 全局唯一标识 |
| name | String | NOT NULL | 用户姓名 |
| role | UserRole | NOT NULL | 角色 |
| email | String | UNIQUE, NOT NULL | 登录账号 |
| passwordHash | String | NOT NULL | bcryptjs 哈希结果 |
| createdAt | DateTime | auto | 创建时间 |
| updatedAt | DateTime | auto | 更新时间 |

### 2.2 Course（课程）

```prisma
model Course {
  id        String    @id @default(cuid())
  name      String
  semester  String
  teacherId String
  createdAt DateTime  @default(now())
  updatedAt DateTime  @updatedAt

  teacher  User       @relation("CourseTeacher", fields: [teacherId], references: [id])
  projects Project[]

  @@index([teacherId])
  @@index([semester])
}
```

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | String | PK, cuid() | 课程唯一标识 |
| name | String | NOT NULL | 课程名称 |
| semester | String | NOT NULL | 学期（如 "2026春"） |
| teacherId | String | FK → User | 负责教师 |

### 2.3 Project（项目）

```prisma
model Project {
  id          String        @id @default(cuid())
  courseId    String
  teamId      String?       // 初期可为空（未组队）
  title       String
  description String?
  status      ProjectStatus @default(preparing)
  createdAt   DateTime      @default(now())
  updatedAt   DateTime      @updatedAt

  course  Course   @relation(fields: [courseId], references: [id])
  team    Team?    @relation(fields: [teamId], references: [id])
  tasks   Task[]
  risks   Risk[]

  @@index([courseId])
  @@index([teamId])
  @@index([status])
}
```

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | String | PK, cuid() | 项目唯一标识 |
| courseId | String | FK → Course | 所属课程 |
| teamId | String? | FK → Team, nullable | 所属团队（未组队时为空） |
| title | String | NOT NULL | 项目名称 |
| description | String? | nullable | 项目描述 |
| status | ProjectStatus | default: preparing | 项目状态 |

### 2.4 Team（团队）

```prisma
model Team {
  id         String   @id @default(cuid())
  name       String
  leaderId   String
  maxMembers Int      @default(6)
  createdAt  DateTime @default(now())
  updatedAt  DateTime @updatedAt

  leader       User              @relation("TeamLeader", fields: [leaderId], references: [id])
  members      TeamMember[]
  applications TeamApplication[]
  project      Project?

  @@index([leaderId])
}
```

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | String | PK, cuid() | 团队唯一标识 |
| name | String | NOT NULL | 团队名称 |
| leaderId | String | FK → User | 负责人 |
| maxMembers | Int | default: 6 | 最大成员数 |

### 2.5 TeamMember（团队成员）

```prisma
model TeamMember {
  id       String   @id @default(cuid())
  teamId   String
  userId   String
  joinedAt DateTime @default(now())

  team Team @relation(fields: [teamId], references: [id])
  user User @relation(fields: [userId], references: [id])

  @@unique([teamId, userId])
  @@index([userId])
}
```

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | String | PK, cuid() | — |
| teamId | String | FK → Team | 所属团队 |
| userId | String | FK → User | 成员 |
| joinedAt | DateTime | auto | 加入时间 |

> `@@unique([teamId, userId])` 确保同一用户不能重复加入同一团队。

### 2.6 TeamApplication（入队申请）

```prisma
model TeamApplication {
  id         String            @id @default(cuid())
  teamId     String
  senderId   String
  reason     String?
  status     ApplicationStatus @default(pending)
  createdAt  DateTime          @default(now())
  updatedAt  DateTime          @updatedAt

  team   Team @relation(fields: [teamId], references: [id])
  sender User @relation("ApplicationSender", fields: [senderId], references: [id])

  @@index([teamId])
  @@index([senderId])
}
```

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | String | PK, cuid() | — |
| teamId | String | FK → Team | 申请加入的团队 |
| senderId | String | FK → User | 申请人 |
| reason | String? | nullable | 申请理由 |
| status | ApplicationStatus | default: pending | 处理状态 |

### 2.7 Task（任务）

```prisma
model Task {
  id          String     @id @default(cuid())
  projectId   String
  assigneeId  String
  title       String
  description String?
  dueAt       DateTime
  status      TaskStatus @default(todo)
  progress    Int        @default(0)  // 0-100
  priority    Int        @default(1)  // 1=低, 2=中, 3=高
  createdAt   DateTime   @default(now())
  updatedAt   DateTime   @updatedAt

  project  Project @relation(fields: [projectId], references: [id])
  assignee User    @relation("TaskAssignee", fields: [assigneeId], references: [id])
  risks    Risk[]

  @@index([projectId])
  @@index([assigneeId])
  @@index([status, dueAt])
}
```

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | String | PK, cuid() | 任务唯一标识 |
| projectId | String | FK → Project | 所属项目 |
| assigneeId | String | FK → User | 责任人 |
| title | String | NOT NULL | 任务标题 |
| description | String? | nullable | 任务描述 |
| dueAt | DateTime | NOT NULL | 截止时间 |
| status | TaskStatus | default: todo | 状态 |
| progress | Int | default: 0, 0–100 | 完成百分比 |
| priority | Int | default: 1 | 优先级（1=低, 2=中, 3=高） |

> `@@index([status, dueAt])` 联合索引支持按状态和截止时间高效筛选逾期任务。

### 2.8 Notification（通知）

```prisma
model Notification {
  id            String           @id @default(cuid())
  receiverId    String
  type          NotificationType
  title         String
  content       String?
  relatedTaskId String?
  confirmedAt   DateTime?        // null 表示未确认
  createdAt     DateTime         @default(now())

  receiver User @relation("NotificationReceiver", fields: [receiverId], references: [id])

  @@index([receiverId, confirmedAt])
  @@index([type])
}
```

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | String | PK, cuid() | — |
| receiverId | String | FK → User | 通知接收人 |
| type | NotificationType | NOT NULL | 通知类型 |
| title | String | NOT NULL | 通知标题 |
| content | String? | nullable | 通知正文 |
| relatedTaskId | String? | nullable | 关联任务 ID |
| confirmedAt | DateTime? | nullable | 确认时间（null=未确认） |

### 2.9 Risk（风险）

```prisma
model Risk {
  id              String    @id @default(cuid())
  projectId       String
  level           RiskLevel
  reason          String
  relatedTaskId   String?
  resolvedAt      DateTime?
  createdByUserId String?
  createdAt       DateTime  @default(now())

  project      Project @relation(fields: [projectId], references: [id])
  relatedTask  Task?   @relation(fields: [relatedTaskId], references: [id])
  createdByUser User?  @relation("RiskCreatedBy", fields: [createdByUserId], references: [id])

  @@index([projectId, resolvedAt])
  @@index([level])
}
```

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | String | PK, cuid() | — |
| projectId | String | FK → Project | 所属项目 |
| level | RiskLevel | NOT NULL | 风险等级 |
| reason | String | NOT NULL | 风险原因描述 |
| relatedTaskId | String? | FK → Task, nullable | 关联任务 |
| resolvedAt | DateTime? | nullable | 风险消除时间（null=活跃） |
| createdByUserId | String? | FK → User, nullable | 创建者（系统生成则为 null） |

> `createdByUserId` 区分是系统自动生成的风险（null）还是教师手动创建的风险（教师 ID）。

### 2.10 ContributionRecord（贡献度记录）

```prisma
model ContributionRecord {
  id                String   @id @default(cuid())
  userId            String
  projectId         String
  taskCompletionRate  Float  @default(0)   // 任务完成率 0-1
  delayCount        Int     @default(0)    // 延期次数
  confirmResponseRate Float  @default(0)   // 通知确认率 0-1
  stageScore        Float   @default(0)    // 阶段提交分（教师手动给）
  referenceScore    Float   @default(0)    // 系统参考分 0-100
  adjustedScore     Float?                 // 教师调整后分数
  createdAt         DateTime @default(now())
  updatedAt         DateTime @updatedAt

  user        User                      @relation(fields: [userId], references: [id])
  adjustments ContributionAdjustment[]

  @@unique([userId, projectId])
  @@index([projectId])
}
```

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | String | PK, cuid() | — |
| userId | String | FK → User | 成员 |
| projectId | String | — | 所属项目 |
| taskCompletionRate | Float | default: 0 | 任务完成率（0-1） |
| delayCount | Int | default: 0 | 累计延期次数 |
| confirmResponseRate | Float | default: 0 | 通知确认率（0-1） |
| stageScore | Float | default: 0 | 阶段提交教师打分 |
| referenceScore | Float | default: 0 | 系统参考分（0-100） |
| adjustedScore | Float? | nullable | 教师调整分（null=未调整） |

> `@@unique([userId, projectId])` 确保每个用户在每个项目中只有一条贡献度记录。

### 2.11 ContributionAdjustment（贡献度调整记录）

```prisma
model ContributionAdjustment {
  id         String   @id @default(cuid())
  recordId   String
  adjustedBy String   // 教师 ID
  oldScore   Float?
  newScore   Float
  reason     String   // 必填，调整理由
  createdAt  DateTime @default(now())

  record         ContributionRecord @relation(fields: [recordId], references: [id])
  adjustedByUser User               @relation(fields: [adjustedBy], references: [id])

  @@index([recordId])
}
```

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | String | PK, cuid() | — |
| recordId | String | FK → ContributionRecord | 所属贡献度记录 |
| adjustedBy | String | FK → User | 调整教师 ID |
| oldScore | Float? | nullable | 调整前分数 |
| newScore | Float | NOT NULL | 调整后分数 |
| reason | String | NOT NULL | 调整理由（必填） |

> `reason` 为必填字段，确保每次调整都有据可查，满足需求中"调整必须记录理由"的业务规则。

### 2.12 SystemConfig（系统配置）

```prisma
model SystemConfig {
  id    String @id @default(uuid())
  key   String @unique
  value String
}
```

Key-Value 模型，存储系统级配置项：

| 典型 key | 说明 | 示例 value |
|----------|------|-----------|
| `risk.overdue.high.days` | 逾期高风险天数阈值 | `3` |
| `risk.silence.days` | 沉默风险天数阈值 | `7` |
| `risk.low_progress.threshold` | 低进度百分比阈值 | `30` |
| `notification.unconfirmed.hours` | 未确认通知告警小时数 | `48` |

### 2.13 NotificationTemplate（通知模板）

```prisma
model NotificationTemplate {
  id      String           @id @default(cuid())
  type    NotificationType
  title   String
  content String
}
```

管理员可编辑的标准通知文案模板。

## 3. 实体关系图（ER）

```
User ──┬── Course (teacherId)              ── Project ── Task ── Risk
       ├── Team (leaderId)                 ── Course                   ├── relatedTask (Task)
       ├── TeamMember (userId)                                        └── createdByUser (User)
       ├── Task (assigneeId)
       ├── Notification (receiverId)
       ├── ContributionRecord (userId) ──── ContributionAdjustment (adjustedBy → User)
       ├── Risk (createdByUserId)
       └── TeamApplication (senderId)

Course ── Project ── Team ── TeamMember ── User
                         └── TeamApplication

         Project ── Risk ←── Task
         Project ── ContributionRecord ←── User
         User ── Notification
```

### 关系基数

| 关系 | 基数 | 说明 |
|------|------|------|
| User → Course | 1:N | 一个教师负责多门课程 |
| Course → Project | 1:N | 一门课程包含多个项目 |
| Project → Team | 1:1 | 一个项目对应一个团队（可选） |
| Team → TeamMember | 1:N | 一个团队有多名成员 |
| Project → Task | 1:N | 一个项目包含多个任务 |
| Task → User (assignee) | N:1 | 一个任务对应一个责任人 |
| User → Notification | 1:N | 一个用户收到多条通知 |
| Project → Risk | 1:N | 一个项目可能有多条风险 |
| User → ContributionRecord | 1:N | 一个用户在不同项目中有不同贡献度记录 |

## 4. 索引策略

| 表 | 索引 | 用途 |
|----|------|------|
| User | `email` (unique) | 登录查询 |
| User | `role` | 管理员按角色筛选用户 |
| Course | `teacherId` | 教师查询自己负责的课程 |
| Course | `semester` | 按学期筛选课程 |
| Project | `courseId` | 课程下项目列表 |
| Project | `teamId` | 通过团队查找项目 |
| Project | `status` | 按状态筛选项目 |
| Team | `leaderId` | 查找某人负责的团队 |
| TeamMember | `(teamId, userId)` (unique) | 防重复 + 查团队成员 |
| TeamMember | `userId` | 查找某人的所有团队 |
| TeamApplication | `teamId` | 查看团队申请列表 |
| TeamApplication | `senderId` | 查看某人发出的申请 |
| Task | `projectId` | 项目下任务列表 |
| Task | `assigneeId` | 个人待办 |
| Task | `(status, dueAt)` | 风险引擎扫描逾期任务 |
| Notification | `(receiverId, confirmedAt)` | 用户通知列表 + 未确认筛选 |
| Notification | `type` | 按类型筛选通知 |
| Risk | `(projectId, resolvedAt)` | 项目活跃风险 |
| Risk | `level` | 按等级筛选风险 |
| ContributionRecord | `(userId, projectId)` (unique) | 用户在某项目中的贡献度 |
| ContributionRecord | `projectId` | 项目所有成员贡献度 |
| ContributionAdjustment | `recordId` | 贡献度调整历史 |
| SystemConfig | `key` (unique) | 配置项查询 |

## 5. 迁移策略（SQLite → PostgreSQL）

当前使用 SQLite 作为开发数据库，Prisma 抽象层使得迁移到 PostgreSQL 只需要：

1. 修改 `prisma/schema.prisma` 中的 `datasource provider` 从 `"sqlite"` 到 `"postgresql"`
2. 修改 `DATABASE_URL` 环境变量为 PostgreSQL 连接串
3. 运行 `npx prisma migrate dev --create-only` 生成迁移脚本
4. 检查并手动调整 SQLite 特定语法的迁移（如有）

**SQLite 与 PostgreSQL 已知差异**：

| 特性 | SQLite | PostgreSQL | 应对 |
|------|--------|------------|------|
| 主键默认值 | `@default(cuid())` 正常 | `@default(cuid())` 正常 | 无需修改 |
| 布尔类型 | 无原生 Boolean（用 Int） | 原生 Boolean | Schema 中统一用枚举/Int，不依赖 Boolean |
| 日期类型 | 无时区概念 | `TIMESTAMPTZ` | 应用层统一使用 UTC |
| 并发写入 | 串行化 | MVCC | 首版 SQLite 单文件足够，升级后自然获得并发能力 |

> 首版不引入 PostgreSQL 以减少环境依赖复杂度。迁移路径已验证可行，后续仅需配置变更。
