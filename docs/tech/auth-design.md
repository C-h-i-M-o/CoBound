# 认证与权限设计

## 1. 认证方案

### 1.1 整体流程

```
注册：用户提交 name/email/password/role
  → bcryptjs 哈希密码
    → Prisma 创建 User
      → 返回用户信息

登录：用户提交 email/password
  → Prisma 查询 User（by email）
    → bcryptjs 验证密码
      → jose 签发 JWT（payload: { userId, role }，7 天过期）
        → Set-Cookie: cobound-session=<jwt>; HttpOnly; Secure; SameSite=Lax; Max-Age=604800
          → 返回用户信息

请求鉴权：
  → middleware.ts 拦截请求
    → 从 Cookie 中读取 cobound-session
      → jose 验证 JWT 签名和过期时间
        → 提取 { userId, role }
          → 根据路由前缀做角色校验
            → 通过 → 继续处理
            → 拒绝 → 返回 401/403

登出：
  → 清除 Cookie（Set-Cookie: cobound-session=; Max-Age=0）
```

### 1.2 Cookie 策略

| 属性 | 值 | 说明 |
|------|----|------|
| Name | `cobound-session` | 会话 Cookie 名称 |
| HttpOnly | true | JS 不可访问，防止 XSS 窃取 |
| Secure | 生产环境 true | 仅 HTTPS 传输 |
| SameSite | Lax | 允许同站请求携带，防止 CSRF |
| Max-Age | 604800（7 天） | 与 JWT 过期时间一致 |
| Path | / | 全站可用 |

### 1.3 JWT 结构

```typescript
// Payload
interface JWTPayload {
  userId: string;   // User.id
  role: UserRole;   // student | teacher | admin
}

// 签发
const jwt = await new SignJWT({ userId, role })
  .setProtectedHeader({ alg: "HS256" })
  .setIssuedAt()
  .setExpirationTime("7d")
  .sign(new TextEncoder().encode(process.env.JWT_SECRET));

// 验证
const { payload } = await jwtVerify(jwt, new TextEncoder().encode(process.env.JWT_SECRET));
```

### 1.4 密码安全

- 使用 `bcryptjs` 进行哈希，saltRounds = 10
- 注册时密码最少 6 位（zod 校验）
- 登录失败不区分"用户不存在"和"密码错误"，统一返回"邮箱或密码错误"
- 密码哈希不通过 API 返回，Prisma 查询默认排除 `passwordHash` 字段

## 2. 模块实现

### 2.1 `src/lib/auth.ts` — 核心认证函数

```typescript
// 函数签名
export async function register(data: RegisterInput): Promise<Omit<User, "passwordHash">>
export async function login(email: string, password: string): Promise<{ user: Omit<User, "passwordHash">; cookie: string }>
export async function getSession(request: NextRequest): Promise<{ userId: string; role: UserRole } | null>
export function createLogoutCookie(): string
export async function hashPassword(plain: string): Promise<string>
export async function verifyPassword(plain: string, hash: string): Promise<boolean>
```

- `register()` — zod 校验 → 查重 email → bcryptjs 哈希 → Prisma create → 返回不含 passwordHash 的 user
- `login()` — zod 校验 → 查 User → 验证密码 → 签发 JWT → 构建 Set-Cookie 字符串 → 返回 user + cookie
- `getSession()` — 从 `request.cookies` 获取 `cobound-session` → jwtVerify → 返回 payload 或 null
- `createLogoutCookie()` — 返回清除 Cookie 的 Set-Cookie 字符串

### 2.2 `src/middleware.ts` — 路由级拦截

```typescript
// 拦截逻辑
export function middleware(request: NextRequest) {
  const path = request.nextUrl.pathname;

  // 公开路由：放行
  if (path.startsWith("/api/auth/") || path === "/login" || path === "/register") {
    return NextResponse.next();
  }

  // 静态资源：放行
  if (path.startsWith("/_next/") || path.startsWith("/favicon")) {
    return NextResponse.next();
  }

  const session = await getSession(request);

  // 未登录：拦截 API 返回 401，页面重定向到登录页
  if (!session) {
    if (path.startsWith("/api/")) {
      return NextResponse.json({ success: false, error: "请先登录" }, { status: 401 });
    }
    return NextResponse.redirect(new URL("/login", request.url));
  }

  // 角色校验
  if (path.startsWith("/api/admin/") || path.startsWith("/admin")) {
    if (session.role !== "admin") {
      return NextResponse.json({ success: false, error: "无权限" }, { status: 403 });
    }
  }

  if (path.startsWith("/teacher") && session.role !== "teacher") {
    return NextResponse.redirect(new URL(`/${session.role}/dashboard`, request.url));
  }

  if (path.startsWith("/student") && session.role !== "student") {
    return NextResponse.redirect(new URL(`/${session.role}/dashboard`, request.url));
  }

  // 登录后访问根路径 → 按角色跳转
  if (path === "/") {
    return NextResponse.redirect(new URL(`/${session.role}/dashboard`, request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"],
};
```

### 2.3 `src/lib/permissions.ts` — 数据级权限

```typescript
// 角色基础鉴权
export function requireRole(session: Session | null, ...roles: UserRole[]): asserts session is Session

// 课程范围鉴权
export async function requireCourseAccess(userId: string, courseId: string): Promise<{
  role: UserRole;
  isTeacher: boolean;  // 当前用户是否该课程教师
  teamId?: string;     // 学生所属团队 ID（如有）
}>
// teacher: 检查 course.teacherId === userId
// student: 检查该课程下是否有团队包含此用户
// admin: 直接通过

// 项目范围鉴权
export async function requireProjectAccess(userId: string, projectId: string): Promise<{
  role: UserRole;
  project: Project;
  isMember: boolean;   // 是否为项目团队成员
}>
// 路径：project → team → members (for student)
//       project → course → teacherId (for teacher)

// 团队操作鉴权
export async function requireTeamLeader(userId: string, teamId: string): Promise<boolean>
// 检查 team.leaderId === userId

// 任务操作鉴权
export async function requireTaskAssignee(userId: string, taskId: string): Promise<boolean>
// 检查 task.assigneeId === userId
```

## 3. 三层权限体系

```
Layer 1: 角色级 (middleware.ts)
  └── 根据 URL 前缀做第一次过滤
      /admin/* → admin only
      /teacher/* → teacher only
      /student/* → student only

Layer 2: 路由级 (permissions.ts)
  └── API Route Handler 入口做第二次校验
      requireRole(session, "admin")
      requireCourseAccess(userId, courseId)
      etc.

Layer 3: 数据级 (permissions.ts + Prisma where)
  └── 查询时通过 Prisma where 条件做最终隔离
      // 学生只查自己团队的任务
      prisma.task.findMany({
        where: {
          project: { team: { members: { some: { userId } } } }
        }
      })
```

### 3.1 权限矩阵

| 操作 | 谁可以做 | 校验层 |
|------|---------|--------|
| 浏览所有课程 | 已登录用户 | Layer 1 |
| 创建课程 | teacher / admin | Layer 1 + 2 |
| 编辑课程 | 负责教师 / admin | Layer 2 (requireCourseAccess) |
| 删除课程 | 仅 admin | Layer 1 |
| 创建团队 | student | Layer 1 |
| 审批入队申请 | teamLeader | Layer 2 (requireTeamLeader) |
| 创建/编辑任务 | teamLeader | Layer 2 |
| 更新任务进度 | 任务 assignee | Layer 2 (requireTaskAssignee) |
| 查看项目详情 | 项目团队 + 课程教师 | Layer 2 (requireProjectAccess) |
| 查看贡献度 | 课程负责教师 | Layer 2 (requireCourseAccess) |
| 调整贡献度 | 课程负责教师 | Layer 2 |
| 管理用户 | admin | Layer 1 |
| 系统配置 | admin | Layer 1 |

## 4. Session 生命周期

```
创建：POST /api/auth/login 成功后
刷新：无自动刷新机制。客户端每次请求时通过 getSession() 验证 JWT 有效性
过期：JWT 内置 7 天过期 + Cookie Max-Age 7 天
销毁：POST /api/auth/logout 清除 Cookie
异常处理：API 返回 401 时，客户端 SWR 捕获并跳转登录页
```

## 5. 安全考量

| 风险 | 对策 |
|------|------|
| XSS 窃取 Token | Cookie HttpOnly，JS 不可读 |
| CSRF 攻击 | SameSite=Lax + Origin 头校验 |
| JWT 泄露 | 短期 7 天 + 无刷新机制（鼓励重新登录） |
| 密码暴力破解 | bcryptjs 哈希（计算成本高，天然抗暴力） |
| IDOR（不安全的直接对象引用） | 数据级权限检查，不依赖前端隐藏 URL |
| 越权访问 | 三层权限体系，确保每个数据查询都通过权限过滤 |
| 敏感信息泄露 | 环境变量管理 JWT_SECRET，不提交到 Git |
