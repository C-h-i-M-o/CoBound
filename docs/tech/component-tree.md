# 组件树与页面路由设计

## 1. Next.js App Router 路由树

```
src/app/
├── layout.tsx                              # 根布局（AuthProvider + TopNav）
├── page.tsx                                # 首页（登录后重定向到角色 Dashboard）
│
├── (auth)/                                 # 认证页面组（公开，无角色校验）
│   ├── login/page.tsx                      # 登录页
│   └── register/page.tsx                   # 注册页
│
├── (student)/                              # 学生端页面组（middleware 校验 student role）
│   ├── layout.tsx                          # 学生端共享布局
│   ├── dashboard/page.tsx                  # 学生首页（课程卡片 + 今日待办）
│   ├── courses/
│   │   ├── page.tsx                        # 课程列表
│   │   └── [courseId]/
│   │       ├── page.tsx                    # 课程详情（项目列表 + 团队列表）
│   │       ├── projects/
│   │       │   └── [projectId]/
│   │       │       ├── page.tsx            # 项目空间（总览 + 双栏）
│   │       │       ├── tasks/
│   │       │       │   ├── page.tsx        # 任务列表/看板
│   │       │       │   └── [taskId]/
│   │       │       │       └── page.tsx    # 任务详情
│   │       │       └── members/
│   │       │           └── page.tsx        # 成员列表
│   │       └── team-intent/
│   │           └── page.tsx                # 组队意向广场
│   └── notifications/
│       └── page.tsx                        # 通知中心
│
├── (teacher)/                              # 教师端页面组（middleware 校验 teacher role）
│   ├── layout.tsx                          # 教师端共享布局
│   ├── dashboard/page.tsx                  # 教师首页（课程统计 + 风险概览）
│   ├── courses/
│   │   ├── page.tsx                        # 负责课程列表
│   │   └── [courseId]/
│   │       ├── page.tsx                    # 团队总览
│   │       ├── teams/
│   │       │   └── [teamId]/
│   │       │       └── page.tsx            # 团队详情
│   │       └── risks/
│   │           └── page.tsx                # 风险预警汇总
│   └── contributions/
│       └── [projectId]/
│           └── page.tsx                    # 贡献度管理
│
└── (admin)/                                # 管理员页面组（middleware 校验 admin role）
    ├── layout.tsx                          # 管理员端共享布局
    ├── dashboard/page.tsx                  # 管理员首页（数据概览）
    ├── users/
    │   ├── page.tsx                        # 用户列表
    │   └── [userId]/
    │       └── page.tsx                    # 用户详情/编辑
    ├── courses/
    │   └── page.tsx                        # 课程管理
    ├── notifications/
    │   └── templates/
    │       └── page.tsx                    # 通知模板管理
    └── settings/
        └── page.tsx                        # 系统配置
```

## 2. 共享布局组件

### 2.1 RootLayout（`src/app/layout.tsx`）

```tsx
// 结构
<html>
  <body>
    <AuthProvider>     {/* Context: 提供 session, user, login, logout */}
      <TopNav />       {/* 顶部导航（所有页面共享） */}
      {children}       {/* 页面内容 */}
    </AuthProvider>
  </body>
</html>
```

### 2.2 TopNav

```
TopNav
├── Logo + "协界 CoBound"（链接到对应角色 Dashboard）
├── 面包屑 / 页面标题
├── 通知图标
│   └── Badge（未确认通知数量，SWR 定时轮询）
└── 用户头像 / 姓名
    └── 下拉菜单
        ├── 个人设置（预留）
        └── 退出登录
```

### 2.3 各端布局差异

| 端 | 导航方式 | 响应式处理 |
|----|---------|-----------|
| 学生端 | 顶部导航 + 侧边栏（桌面）/ 底部 TabBar（手机） | < 768px 切换为底部 TabBar：首页、课程、通知、我的 |
| 教师端 | 顶部导航 + 侧边栏 | < 768px 侧边栏折叠为汉堡菜单 |
| 管理员端 | 顶部导航 + 侧边栏 | < 768px 侧边栏折叠为汉堡菜单 |

## 3. 页面组件层级

### 3.1 学生端

#### Dashboard（`/student/dashboard`）

```
StudentDashboard
├── PageHeader（"我的协界"）
├── CourseProjectCards           # SWR: /api/courses + /api/projects
│   └── CourseCard[]
│       ├── 课程名称 + 学期
│       ├── 项目名称 + ProgressBar
│       └── RiskIndicator（综合风险等级）
├── TodayTasks                   # SWR: /api/notifications?confirmed=false + 个人待办任务
│   ├── TaskItem[]
│   │   ├── 确认状态图标
│   │   ├── 任务标题
│   │   ├── DeadlineTimer
│   │   └── ConfirmButton（若未确认）
│   └── EmptyState（无待办时）
└── QuickActions
    ├── Button → 发布组队意向
    └── Button → 浏览课程
```

#### 项目空间（`/student/courses/[courseId]/projects/[projectId]`）

```
ProjectSpace
├── PageHeader（项目名称 + 状态 Badge）
├── ProjectOverviewCard
│   ├── 项目标题、描述
│   ├── ProgressBar（总进度）
│   ├── RiskIndicator（当前最高风险等级）
│   └── 团队信息
├── TabNavigation[概况 | 任务 | 成员 | 风险]
└── TabContent
    ├── Tab-概况
    │   ├── 双栏布局
    │   ├── 左栏：TaskBoard（四列看板）
    │   │   └── TaskColumn[todo, doing, done, overdue]
    │   │       └── TaskCard[]
    │   │           ├── 标题
    │   │           ├── 负责人头像 + 姓名
    │   │           ├── PriorityBadge
    │   │           └── DeadlineTimer
    │   └── 右栏：RiskReminderPanel
    │       ├── RiskItem[]
    │       │   ├── RiskBadge（level）
    │       │   ├── reason
    │       │   └── 关联任务链接
    │       └── UnconfirmedNotificationCount
    ├── Tab-任务 → 同 TaskBoard 全屏模式 + 筛选 + 新建按钮
    ├── Tab-成员
    │   └── MemberList
    │       └── MemberItem[]
    │           ├── 姓名
    │           ├── 角色（负责人/成员）
    │           └── 完成任务数
    └── Tab-风险
        └── RiskList（筛选 + 排序）
```

#### 任务详情（`.../tasks/[taskId]`）

```
TaskDetail
├── PageHeader（任务标题 + 状态 Badge）
├── TaskInfo
│   ├── 标题、描述
│   ├── 负责人（姓名 + 头像）
│   ├── 优先级 Badge
│   └── DeadlineTimer（截止时间 + 剩余/逾期天数）
├── ProgressSection
│   ├── ProgressBar（当前进度）
│   └── ProgressUpdateForm
│       ├── Slider/Input（0-100）
│       ├── StatusSelect（todo/doing/done）
│       └── SubmitButton
├── ConfirmButton（若未确认此任务的通知）
└── RelatedNotifications
    └── NotificationItem[]
```

### 3.2 教师端

#### 团队总览（`/teacher/courses/[courseId]`）

```
CourseOverview
├── PageHeader（课程名称 + 学期）
├── CourseStatsBar（项目数、团队数、高风险数）
├── TabNavigation[团队总览 | 风险预警 | 贡献度]
└── TabContent
    ├── Tab-团队总览
    │   ├── FilterBar（风险等级、进度范围、搜索）
    │   └── TeamOverviewTable
    │       └── Row[]
    │           ├── 团队名称
    │           ├── ProgressBar + 百分比
    │           ├── RiskBadge（high/medium/low）
    │           ├── 未确认数 Badge
    │           ├── 成员数
    │           └── Button → 查看详情
    ├── Tab-风险预警
    │   └── RiskSummary（按等级分组 + 团队列表）
    └── Tab-贡献度（链接到贡献度管理页）
```

#### 贡献度管理（`/teacher/contributions/[projectId]`）

```
ContributionPage
├── PageHeader（项目名称 + "贡献度管理"）
├── ContributionTable
│   └── Row[]
│       ├── 姓名
│       ├── 完成率（百分比）
│       ├── 延期次数
│       ├── 确认率（百分比）
│       ├── 阶段分
│       ├── 参考分（系统计算，灰色显示）
│       ├── 调整分（教师设定，高亮显示；未调整则空）
│       └── Button → 调整（打开 Modal）
├── ContributionForm（Modal）
│   ├── 当前分数展示
│   ├── Input（新分数 0-100）
│   ├── TextArea（调整理由，必填）
│   └── Submit + Cancel
└── AdjustmentHistory
    └── AdjustmentItem[]
        ├── 调整教师
        ├── 旧分 → 新分
        ├── 理由
        └── 时间
```

### 3.3 管理员端

```
AdminLayout
├── Sidebar
│   ├── 数据概览
│   ├── 用户管理
│   ├── 课程管理
│   ├── 通知模板
│   └── 系统配置
└── Content
    ├── Dashboard → 统计卡片（用户数、课程数、项目数）
    ├── UserList → UserTable + SearchBar + Pagination
    ├── CourseList → CourseTable + CourseForm(Modal)
    ├── TemplateList → TemplateEditor(Modal)
    └── SettingsForm → KeyValueEditor
```

## 4. 共享组件清单

### 4.1 UI 原子组件（`components/ui/`）

| 组件 | Props | 用途 |
|------|-------|------|
| `Button` | `variant`, `size`, `disabled`, `loading`, `onClick` | 通用按钮 |
| `Card` | `title?`, `children`, `className` | 通用卡片容器 |
| `Badge` | `variant` (info/success/warning/danger), `children` | 标签/徽标 |
| `Modal` | `open`, `onClose`, `title`, `children` | 弹窗 |
| `Table` | `columns`, `data`, `sortable`, `onSort`, `pagination` | 通用表格 |
| `Tabs` | `items: { key, label }[]`, `activeKey`, `onChange` | 标签页切换 |
| `ProgressBar` | `value` (0-100), `size` | 进度条（颜色自动：< 30% 红, 30-70% 黄, > 70% 绿） |
| `EmptyState` | `icon?`, `title`, `description` | 空数据占位 |
| `SearchBar` | `placeholder`, `onSearch` | 搜索框 |
| `Pagination` | `page`, `total`, `pageSize`, `onChange` | 分页器 |

### 4.2 共享业务组件（`components/shared/`）

| 组件 | 用途 | 使用者 |
|------|------|--------|
| `RiskIndicator` | 风险等级图标 + 文字（low/medium/high 三色） | 学生、教师 |
| `RiskBadge` | 紧凑型风险等级标签 | 列表行内使用 |
| `ConfirmButton` | 确认按钮（点后变已确认状态） | 学生通知确认 |
| `DeadlineTimer` | "还有 3 天截止" / "已逾期 2 天" | 学生任务、教师风险 |
| `UserAvatar` | 用户头像占位（首字 + 背景色） | 全局 |
| `RoleBadge` | 学生/教师/管理员标签 | 管理员用户列表 |

### 4.3 各端专属组件

| 端 | 组件 | 用途 |
|----|------|------|
| student | `TaskCard` | 任务看板卡片 |
| student | `TaskBoard` | 四列看板容器 |
| student | `TaskColumn` | 单一列（todo/doing/done/overdue） |
| student | `ProgressUpdateForm` | 进度更新表单 |
| student | `TeamApplicationForm` | 申请入队表单 |
| student | `NotificationList` | 通知列表 + 确认 |
| teacher | `TeamOverviewTable` | 团队总览表格 |
| teacher | `ContributionForm` | 贡献度调整弹窗 |
| admin | `UserTable` | 用户管理表格 |
| admin | `CourseForm` | 课程新增/编辑表单 |
| admin | `TemplateEditor` | 通知模板编辑器 |

## 5. 状态管理方案

### 5.1 服务端状态（SWR）

所有来自 API 的数据使用 SWR 管理：

```typescript
// 典型用法
const { data, error, isLoading, mutate } = useSWR<ApiResponse<T>>(
  "/api/courses",
  fetcher
);

// 全局配置（在 AuthProvider 中）
<SWRConfig value={{
  fetcher: (url) => fetch(url).then(r => r.json()),
  revalidateOnFocus: false,     // 首版不做焦点重验证
  refreshInterval: 0,           // 不自动轮询（通知 Badge 除外）
  shouldRetryOnError: true,
  errorRetryCount: 2,
}}>
```

### 5.2 客户端状态（React Context）

仅用于跨组件共享的全局状态：

| Context | 提供 | 消费者 |
|---------|------|--------|
| `AuthContext` | `session`, `user`, `login()`, `logout()`, `isLoading` | 所有组件 |
| `NotificationContext` | `unconfirmedCount` | TopNav 通知 Badge |

### 5.3 表单状态

各表单组件内使用 `useState` 管理局部状态，提交前 zod 校验。

## 6. 响应式断点策略

| 断点 | 宽度 | 布局 |
|------|------|------|
| 手机 | < 768px | 单栏，底部 TabBar 导航 |
| 平板 | 768px - 1023px | 单栏/双栏，侧边栏可折叠 |
| 桌面 | ≥ 1024px | 双栏/三栏，固定侧边栏 |

关键适配点：
- 任务看板：桌面四列并排 → 平板两列 → 手机单列滑动
- 教师总览表格：桌面全列 → 手机隐藏部分列（保留名称、进度、风险）
- Modal：桌面居中 → 手机全屏
