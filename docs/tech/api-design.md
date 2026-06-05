# API 接口设计

## 1. 全局规范

### 1.1 URL 结构

```
/api/{resource}[/{resourceId}]/[sub-resource][/{subResourceId}][/action]
```

示例：
- `/api/courses` — 课程列表
- `/api/courses/abc123` — 课程详情
- `/api/courses/abc123/projects` — 课程下项目列表
- `/api/teams/xyz789/applications` — 团队申请列表
- `/api/notifications/n001/confirm` — 确认通知（动作）

### 1.2 HTTP 方法语义

| 方法 | 语义 | 幂等 |
|------|------|------|
| GET | 查询 | 是 |
| POST | 创建 | 否 |
| PUT | 全量更新 | 是 |
| PATCH | 部分更新 | 否 |
| DELETE | 删除 | 是 |

### 1.3 统一响应格式

```typescript
// 成功
interface SuccessResponse<T> {
  success: true;
  data: T;
}

// 列表（含分页）
interface ListResponse<T> {
  success: true;
  data: T[];
  total: number;
  page: number;
  pageSize: number;
}

// 失败
interface ErrorResponse {
  success: false;
  error: string;
}
```

### 1.4 分页与排序

请求参数：

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `page` | number | 1 | 页码（从 1 开始） |
| `pageSize` | number | 20 | 每页条数（最大 100） |
| `sort` | string | `createdAt` | 排序字段 |
| `order` | `asc` \| `desc` | `desc` | 排序方向 |

### 1.5 错误码

| HTTP 状态码 | 含义 | 场景 |
|-------------|------|------|
| 200 | 成功 | GET / PUT / PATCH |
| 201 | 已创建 | POST 成功 |
| 204 | 无内容 | DELETE 成功 |
| 400 | 请求参数错误 | zod 校验失败、业务规则校验失败 |
| 401 | 未登录 | Cookie 缺失或过期 |
| 403 | 无权限 | 角色不符、数据范围越权 |
| 404 | 不存在 | 资源未找到 |
| 409 | 冲突 | 唯一约束冲突（如重复申请） |
| 500 | 服务器错误 | 未预期的内部错误 |

## 2. 认证模块（`/api/auth`）

所有 `/api/auth/*` 公开，无需登录。

### POST `/api/auth/register`

```typescript
// Request
{
  name: string;
  email: string;
  password: string;   // 明文，最少 6 位
  role: UserRole;     // 注册时可选择 student/teacher
}
// 注意：admin 角色不能通过注册创建，仅管理员或种子数据创建

// Response 201
{ success: true, data: { id: string; name: string; email: string; role: UserRole } }
// Response 400
{ success: false, error: "该邮箱已被注册" }
```

### POST `/api/auth/login`

```typescript
// Request
{ email: string; password: string; }

// Response 200 — 同时 Set-Cookie: cobound-session=<jwt>
{ success: true, data: { id: string; name: string; role: UserRole } }
// Response 401
{ success: false, error: "邮箱或密码错误" }
```

### GET `/api/auth/session`

```typescript
// 从 Cookie 中解析 JWT 返回当前用户
// Response 200
{ success: true, data: { id: string; name: string; role: UserRole } | null }
// null = 未登录
```

### POST `/api/auth/logout`

```typescript
// 清除 Cookie
// Response 200
{ success: true, data: null }
```

## 3. 课程模块（`/api/courses`）

### GET `/api/courses`

```typescript
// Query: ?semester=&q=
// 权限：已登录。学生看到全部课程；教师只看到自己负责的课程
// Response 200
{
  success: true,
  data: { id: string; name: string; semester: string; teacher: { id: string; name: string }; projectCount: number }[],
  total: number, page: number, pageSize: number
}
```

### POST `/api/courses`

```typescript
// 权限：admin 或 teacher
// Request
{ name: string; semester: string; }
// teacher 创建时自动绑定为当前教师
// admin 创建时需指定 teacherId

// Response 201
{ success: true, data: { id: string; name: string; semester: string; teacherId: string } }
```

### GET `/api/courses/[courseId]`

```typescript
// 权限：已登录（内部做 courseAccess 检查）
// Response 200
{
  success: true,
  data: {
    id: string; name: string; semester: string;
    teacher: { id: string; name: string };
    projectCount: number;
    studentCount: number;
  }
}
```

### PUT `/api/courses/[courseId]`

```typescript
// 权限：admin 或该课程负责教师
// Request
{ name?: string; semester?: string; }

// Response 200
{ success: true, data: Course }
```

### DELETE `/api/courses/[courseId]`

```typescript
// 权限：仅 admin
// Response 200 / 400（有关联项目时禁止删除）
```

### GET `/api/courses/[courseId]/projects`

```typescript
// 权限：courseAccess
// Query: ?status=
// Response 200
{
  success: true,
  data: { id: string; title: string; status: ProjectStatus; team?: { id: string; name: string }; taskCount: number; progress: number }[],
  total: number, page: number, pageSize: number
}
```

## 4. 项目模块（`/api/projects`）

### GET `/api/projects/[projectId]`

```typescript
// 权限：projectAccess
// Response 200
{
  success: true,
  data: {
    id: string; title: string; description: string | null; status: ProjectStatus;
    course: { id: string; name: string };
    team: { id: string; name: string; members: { id: string; name: string }[] } | null;
    totalTasks: number; doneTasks: number; progress: number;
    risks: { id: string; level: RiskLevel; reason: string; resolvedAt: string | null }[];
  }
}
```

### POST `/api/courses/[courseId]/projects`

```typescript
// 权限：courseAccess（教师或已选课学生）
// Request
{ title: string; description?: string; }

// Response 201
{ success: true, data: Project }
```

### PUT `/api/projects/[projectId]`

```typescript
// 权限：projectAccess + teamLeader
// Request
{ title?: string; description?: string; }

// Response 200
```

### PATCH `/api/projects/[projectId]`

```typescript
// 权限：teamLeader 或 teacher
// Request
{ status?: ProjectStatus; }
// 只允许状态流转：preparing → active → archived

// Response 200
```

### GET `/api/projects/[projectId]/tasks`

```typescript
// 权限：projectAccess
// Query: ?status=&assigneeId=&sort=&order=
// Response 200
{
  success: true,
  data: {
    id: string; title: string; status: TaskStatus; progress: number;
    priority: number; dueAt: string;
    assignee: { id: string; name: string };
  }[],
  total: number, page: number, pageSize: number
}
```

### POST `/api/projects/[projectId]/tasks`

```typescript
// 权限：projectAccess + teamLeader（或 team member）
// Request
{ title: string; description?: string; assigneeId: string; dueAt: string; priority?: number; }

// Response 201
// 副作用：自动生成通知给 assignee（type=task）
```

### GET `/api/projects/[projectId]/tasks/[taskId]`

```typescript
// 权限：projectAccess
// Response 200
{
  success: true,
  data: {
    id: string; title: string; description: string | null;
    status: TaskStatus; progress: number; priority: number;
    dueAt: string; createdAt: string; updatedAt: string;
    assignee: { id: string; name: string };
    notifications: { id: string; type: NotificationType; confirmedAt: string | null }[];
  }
}
```

### PUT `/api/projects/[projectId]/tasks/[taskId]`

```typescript
// 权限：teamLeader 或 该任务的 assignee
// Request
{ title?: string; description?: string; assigneeId?: string; dueAt?: string; priority?: number; }

// Response 200
```

### PATCH `/api/projects/[projectId]/tasks/[taskId]/progress`

```typescript
// 权限：该任务的 assignee
// Request
{ progress: number; status?: TaskStatus; }  // progress 0-100

// Response 200
// 副作用：触发 risk-engine.refresh(projectId)
```

### GET `/api/projects/[projectId]/risks`

```typescript
// 权限：projectAccess
// Query: ?level=&resolved=
// Response 200
{
  success: true,
  data: {
    id: string; level: RiskLevel; reason: string;
    relatedTask?: { id: string; title: string } | null;
    resolvedAt: string | null; createdAt: string;
  }[]
}
```

## 5. 团队模块（`/api/teams`）

### GET `/api/teams`

```typescript
// Query: ?courseId= （必填）
// 权限：已登录
// Response 200
{
  success: true,
  data: {
    id: string; name: string; leader: { id: string; name: string };
    memberCount: number; maxMembers: number;
  }[]
}
```

### POST `/api/teams`

```typescript
// 权限：student
// Request
{ name: string; courseId: string; maxMembers?: number; }

// Response 201
// 创建者自动成为 leader + 首个成员
```

### GET `/api/teams/[teamId]`

```typescript
// 权限：已登录（teamAccess 内部判断）
// 教师可查看自己课程下的所有团队详情
// Response 200
{
  success: true,
  data: {
    id: string; name: string; maxMembers: number;
    leader: { id: string; name: string };
    members: { id: string; name: string; joinedAt: string }[];
    project?: { id: string; title: string; status: ProjectStatus } | null;
  }
}
```

### PUT `/api/teams/[teamId]`

```typescript
// 权限：teamLeader
// Request
{ name?: string; maxMembers?: number; }

// Response 200
```

### POST `/api/teams/[teamId]/applications`

```typescript
// 权限：student
// Request
{ reason?: string; }

// Response 201
// Response 409 — 重复申请
{ success: false, error: "您已提交过申请，请等待处理" }
// Response 400 — 团队已满
{ success: false, error: "该团队已满员" }
```

### GET `/api/teams/[teamId]/applications`

```typescript
// 权限：teamLeader
// Query: ?status=
// Response 200
{
  success: true,
  data: {
    id: string; sender: { id: string; name: string };
    reason: string | null; status: ApplicationStatus; createdAt: string;
  }[]
}
```

### PATCH `/api/teams/[teamId]/applications/[appId]`

```typescript
// 权限：teamLeader
// Request
{ status: "accepted" | "rejected"; }

// Response 200
// 副作用：accepted → 将申请人加入团队；生成通知给申请人
```

## 6. 组队意向模块（`/api/team-intents`）

> 组队意向不同于团队申请——它是公开的兴趣声明墙，不绑定到特定团队。

### GET `/api/team-intents`

```typescript
// Query: ?courseId= （必填）
// 权限：已登录
// Response 200
{
  success: true,
  data: {
    id: string; sender: { id: string; name: string };
    content: string; createdAt: string;
  }[]
}
```

> 组队意向数据直接存储在 User 表上的关联字段，或使用单独的轻量表（不在首版 Prisma Schema 中体现，通过 Client 直查）。

### POST `/api/team-intents`

```typescript
// 权限：student
// Request
{ courseId: string; content: string; }
// content: 兴趣 + 技能 + 可投入时间的自由文本

// Response 201
```

## 7. 通知模块（`/api/notifications`）

### GET `/api/notifications`

```typescript
// 权限：已登录（自动过滤当前用户的通知）
// Query: ?type=&confirmed= （confirmed=true 只查已确认）
// Response 200
{
  success: true,
  data: {
    id: string; type: NotificationType; title: string; content: string | null;
    relatedTaskId: string | null; confirmedAt: string | null; createdAt: string;
  }[],
  total: number, page: number, pageSize: number
}
```

### PATCH `/api/notifications/[notificationId]/confirm`

```typescript
// 权限：该通知的接收人
// Response 200
// 副作用：触发 contribution-calc.recalc(userId, projectId) 更新确认率
```

## 8. 贡献度模块（`/api/contributions`）

### GET `/api/contributions/[projectId]`

```typescript
// 权限：该课程的负责教师
// Response 200
{
  success: true,
  data: {
    userId: string; userName: string;
    taskCompletionRate: number; delayCount: number;
    confirmResponseRate: number; stageScore: number;
    referenceScore: number; adjustedScore: number | null;
  }[]
}
// 若 adjustedScore 为空，前端显示 referenceScore；若不为空，显示 adjustedScore
```

### POST `/api/contributions/[projectId]/adjust`

```typescript
// 权限：该课程的负责教师
// Request
{ userId: string; newScore: number; reason: string; }

// Response 200
// 创建 ContributionAdjustment 记录
// 更新 ContributionRecord.adjustedScore
// Response 400 — reason 为空
{ success: false, error: "调整理由不能为空" }
```

## 9. 用户管理模块（`/api/users`）

> 以下接口仅限 admin 角色。

### GET `/api/users`

```typescript
// Query: ?role=&q= （q 搜索姓名或邮箱）
// Response 200
{
  success: true,
  data: { id: string; name: string; email: string; role: UserRole; createdAt: string }[],
  total: number, page: number, pageSize: number
}
```

### POST `/api/users`

```typescript
// Request
{ name: string; email: string; password: string; role: UserRole; }

// Response 201
```

### GET `/api/users/[userId]`

```typescript
// Response 200
{ success: true, data: { id: string; name: string; email: string; role: UserRole; createdAt: string; updatedAt: string } }
```

### PUT `/api/users/[userId]`

```typescript
// Request
{ name?: string; email?: string; role?: UserRole; password?: string; }
// password 可选，仅传入时更新

// Response 200
```

### DELETE `/api/users/[userId]`

```typescript
// Response 200 / 400（有关联数据时禁止删除）
```

## 10. 系统配置模块（`/api/settings`）

> 以下接口仅限 admin 角色。

### GET `/api/settings`

```typescript
// Response 200
{ success: true, data: { key: string; value: string }[] }
```

### PUT `/api/settings`

```typescript
// Request
{ settings: { key: string; value: string }[]; }
// 批量 upsert

// Response 200
```

### GET `/api/settings/notification-templates`

```typescript
// Query: ?type=
// Response 200
{ success: true, data: { id: string; type: NotificationType; title: string; content: string }[] }
```

### PUT `/api/settings/notification-templates/[templateId]`

```typescript
// Request
{ title?: string; content?: string; }

// Response 200
```

## 11. zod Schema 定义（关键）

```typescript
// src/types/api.ts

import { z } from "zod";

// 认证
export const registerSchema = z.object({
  name: z.string().min(1, "姓名不能为空"),
  email: z.string().email("邮箱格式不正确"),
  password: z.string().min(6, "密码至少 6 位"),
  role: z.enum(["student", "teacher"]),
});

export const loginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
});

// 课程
export const createCourseSchema = z.object({
  name: z.string().min(1),
  semester: z.string().min(1),
  teacherId: z.string().optional(), // admin 创建时可选
});

// 项目
export const createProjectSchema = z.object({
  title: z.string().min(1, "项目名称不能为空"),
  description: z.string().optional(),
});

// 任务
export const createTaskSchema = z.object({
  title: z.string().min(1, "任务标题不能为空"),
  description: z.string().optional(),
  assigneeId: z.string().min(1, "必须指定负责人"),
  dueAt: z.string().refine((v) => new Date(v) > new Date(), "截止时间必须在未来"),
  priority: z.number().int().min(1).max(3).optional().default(1),
});

export const updateProgressSchema = z.object({
  progress: z.number().int().min(0).max(100),
  status: z.enum(["todo", "doing", "done"]).optional(),
});

// 团队
export const createTeamSchema = z.object({
  name: z.string().min(1),
  courseId: z.string().min(1),
  maxMembers: z.number().int().min(2).max(20).optional().default(6),
});

export const applicationActionSchema = z.object({
  status: z.enum(["accepted", "rejected"]),
});

// 贡献度
export const adjustContributionSchema = z.object({
  userId: z.string().min(1),
  newScore: z.number().min(0).max(100),
  reason: z.string().min(1, "调整理由不能为空"),
});

// 分页
export const paginationSchema = z.object({
  page: z.coerce.number().int().min(1).optional().default(1),
  pageSize: z.coerce.number().int().min(1).max(100).optional().default(20),
  sort: z.string().optional().default("createdAt"),
  order: z.enum(["asc", "desc"]).optional().default("desc"),
});
```
