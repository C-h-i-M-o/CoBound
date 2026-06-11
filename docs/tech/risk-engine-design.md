# 风险引擎详细设计

## 1. 设计原则

- **按需计算**：不依赖独立定时任务，在用户访问相关页面或关键操作发生时触发
- **结果持久化**：计算出的风险写入 Risk 表，前端直接读取不需重复计算
- **自动清理**：风险消除后自动标记 `resolvedAt`，不删除记录（保留审计轨迹）
- **幂等性**：同一风险多次计算不会产生重复记录（通过去重 Key）
- **纯函数**：风险规则作为纯函数，输入数据 → 输出风险列表，方便单元测试

## 2. 风险规则定义

### R1：逾期任务风险

```
触发条件：
  task.status != "done"
  AND task.dueAt < now()

等级判定：
  high:   逾期超过 3 天  (now() - dueAt > 3 days)
  medium: 逾期 1-3 天     (1 day < now() - dueAt <= 3 days)
  low:    逾期不足 1 天    (now() - dueAt <= 1 day)

原因模板：
  "任务「{taskTitle}」已于 {dueAt} 截止，当前状态为 {status}，已逾期 {days} 天"

关联数据：
  relatedTaskId = task.id
```

### R2：临近 Deadline 风险

```
触发条件：
  task.status != "done"
  AND task.status != "overdue"
  AND 0 <= (task.dueAt - now()) <= 24 小时

等级判定：
  固定 medium

原因模板：
  "任务「{taskTitle}」将于 {dueAt} 截止，距今不足 24 小时，当前进度 {progress}%"

关联数据：
  relatedTaskId = task.id
```

### R3：未确认通知风险

```
触发条件：
  notification.confirmedAt == null
  AND notification.type IN ("task", "deadline")
  AND now() - notification.createdAt > 48 小时

等级判定：
  high:   超过 7 天未确认 且 type == "deadline"
  medium: 超过 48 小时未确认
  low:    仅在汇总报告中使用，不单独生成风险

原因模板（按成员聚合）：
  "成员 {userName} 有 {count} 条重要通知超过 {hours} 小时未确认"

关联数据：
  无（聚合风险，不关联特定任务）
```

### R4：低进度风险

```
触发条件：
  project.totalTasks > 0
  AND projectProgress < 30%
  AND now() - project.createdAt > 3 天
  AND 无 overdue 任务（排除刚启动的项目）

等级判定：
  high:   进度 < 15% 且创建超过 7 天
  medium: 进度 < 30% 且创建超过 3 天

原因模板：
  "项目整体进度仅 {progress}%，创建已 {days} 天，推进速度偏慢"

关联数据：
  无（项目级风险，不关联特定任务）
```

### R5：团队沉默风险

```
触发条件：
  项目内所有任务在最近 7 天内无任何 updatedAt 变更

等级判定：
  low

原因模板：
  "项目近 7 天无任何任务进度更新，团队可能处于停滞状态"

关联数据：
  无
```

## 3. 计算流程

### 3.1 核心函数

```typescript
// src/lib/risk-engine.ts

interface RiskRule {
  name: string;
  check(params: RiskCheckParams): Promise<RiskMatch[]>;
}

interface RiskMatch {
  level: RiskLevel;
  reason: string;
  relatedTaskId?: string;
  dedupKey: string;
}

async function refreshProjectRisks(projectId: string): Promise<Risk[]> {
  // 1. 加载项目上下文
  const project = await prisma.project.findUnique({
    where: { id: projectId },
    include: {
      tasks: { include: { assignee: true } },
      risks: { where: { resolvedAt: null } },  // 当前活跃风险
    },
  });
  if (!project) throw new NotFoundError();

  // 2. 获取未确认通知（用于 R3）
  const unconfirmedNotifications = await prisma.notification.findMany({
    where: {
      relatedTaskId: { in: project.tasks.map(t => t.id) },
      confirmedAt: null,
      type: { in: ["task", "deadline"] },
    },
    include: { receiver: true },
  });

  // 3. 构建待清理集合（当前活跃风险的 dedupKey）
  const existingKeys = new Map(
    project.risks.map(r => [computeDedupKey(r), r])
  );

  // 4. 执行 R1-R5 规则
  const allMatches: RiskMatch[] = [];
  allMatches.push(...checkR1(project.tasks));
  allMatches.push(...checkR2(project.tasks));
  allMatches.push(...checkR3(project.tasks, unconfirmedNotifications));
  allMatches.push(...checkR4(project));
  allMatches.push(...checkR5(project));

  // 5. 去重 & 写入
  const upserted: Risk[] = [];
  for (const match of allMatches) {
    if (existingKeys.has(match.dedupKey)) {
      existingKeys.delete(match.dedupKey); // 风险仍存在，保留
    } else {
      // 新风险 → 写入
      const risk = await prisma.risk.create({
        data: {
          projectId, level: match.level, reason: match.reason,
          relatedTaskId: match.relatedTaskId,
        },
      });
      upserted.push(risk);
    }
  }

  // 6. 清理已消除的旧风险（existingKeys 中剩余的是已不存在的风险）
  if (existingKeys.size > 0) {
    await prisma.risk.updateMany({
      where: { id: { in: [...existingKeys.values()].map(r => r.id) } },
      data: { resolvedAt: new Date() },
    });
  }

  // 7. 返回当前活跃风险
  return prisma.risk.findMany({
    where: { projectId, resolvedAt: null },
    orderBy: { level: "desc" },
  });
}
```

### 3.2 去重策略

去重 Key 生成规则：`{ruleName}-{projectId}-{keyFields}-{reason摘要}`

```typescript
function computeDedupKey(match: RiskMatch): string {
  // 对理由取前 50 字符防止完全相同的 Key 被不同任务生成
  return `${match.dedupKey}`;
}

// 各规则的 dedupKey 示例：
// R1: "R1-cuid123-task456"
// R2: "R2-cuid123-task456"
// R3: "R3-cuid123-user789"
// R4: "R4-cuid123"
// R5: "R5-cuid123"
```

### 3.3 各规则实现

```typescript
function checkR1(tasks: Task[]): RiskMatch[] {
  const now = new Date();
  return tasks
    .filter(t => t.status !== "done" && t.dueAt < now)
    .map(t => {
      const daysOverdue = Math.ceil((now.getTime() - t.dueAt.getTime()) / (86400000));
      return {
        level: daysOverdue > 3 ? "high" : daysOverdue > 1 ? "medium" : "low",
        reason: `任务「${t.title}」已于 ${t.dueAt.toISOString()} 截止，当前状态为 ${t.status}，已逾期 ${daysOverdue} 天`,
        relatedTaskId: t.id,
        dedupKey: `R1-${t.projectId}-${t.id}`,
      };
    });
}

function checkR2(tasks: Task[]): RiskMatch[] {
  const now = new Date();
  const in24Hours = new Date(now.getTime() + 86400000);
  return tasks
    .filter(t => t.status !== "done" && t.status !== "overdue" && t.dueAt > now && t.dueAt <= in24Hours)
    .map(t => ({
      level: "medium" as RiskLevel,
      reason: `任务「${t.title}」将于 ${t.dueAt.toISOString()} 截止，距今不足 24 小时，当前进度 ${t.progress}%`,
      relatedTaskId: t.id,
      dedupKey: `R2-${t.projectId}-${t.id}`,
    }));
}

function checkR3(tasks: Task[], notifications: Notification[]): RiskMatch[] {
  const now = new Date();
  const fortyEightHoursAgo = new Date(now.getTime() - 48 * 3600000);
  const sevenDaysAgo = new Date(now.getTime() - 7 * 86400000);

  // 按接收人聚合
  const byReceiver = new Map<string, { notifications: Notification[]; userName: string }>();
  for (const n of notifications) {
    if (!byReceiver.has(n.receiverId)) {
      byReceiver.set(n.receiverId, { notifications: [], userName: n.receiver.name });
    }
    byReceiver.get(n.receiverId)!.notifications.push(n);
  }

  const matches: RiskMatch[] = [];
  for (const [userId, { notifications: userNotifs, userName }] of byReceiver.entries()) {
    const recentNotifs = userNotifs.filter(n => n.createdAt < fortyEightHoursAgo);
    if (recentNotifs.length === 0) continue;

    const veryOldNotifs = recentNotifs.filter(n => n.createdAt < sevenDaysAgo && n.type === "deadline");
    const level = veryOldNotifs.length > 0 ? "high" : "medium";
    const hours = level === "high" ? 168 : 48;

    matches.push({
      level,
      reason: `成员 ${userName} 有 ${recentNotifs.length} 条重要通知超过 ${hours} 小时未确认`,
      dedupKey: `R3-${tasks[0].projectId}-${userId}`,
    });
  }
  return matches;
}

function checkR4(project: Project & { tasks: Task[] }): RiskMatch[] {
  const now = new Date();
  const daysSinceCreation = Math.ceil((now.getTime() - project.createdAt.getTime()) / 86400000);
  const totalTasks = project.tasks.length;
  if (totalTasks === 0) return [];

  const doneTasks = project.tasks.filter(t => t.status === "done").length;
  const progress = (doneTasks / totalTasks) * 100;

  const hasOverdue = project.tasks.some(t => t.status === "overdue");
  if (hasOverdue || progress >= 30 || daysSinceCreation <= 3) return [];

  return [{
    level: progress < 15 && daysSinceCreation > 7 ? "high" : "medium",
    reason: `项目整体进度仅 ${Math.round(progress)}%，创建已 ${daysSinceCreation} 天，推进速度偏慢`,
    dedupKey: `R4-${project.id}`,
  }];
}

function checkR5(project: Project & { tasks: Task[] }): RiskMatch[] {
  const now = new Date();
  const sevenDaysAgo = new Date(now.getTime() - 7 * 86400000);

  if (project.tasks.length === 0) return [];

  const anyUpdated = project.tasks.some(t => t.updatedAt > sevenDaysAgo);
  if (anyUpdated) return [];

  return [{
    level: "low",
    reason: "项目近 7 天无任何任务进度更新，团队可能处于停滞状态",
    dedupKey: `R5-${project.id}`,
  }];
}
```

## 4. 触发时机

| 事件 | 触发方式 | 说明 |
|------|---------|------|
| 学生访问项目空间 | `refreshProjectRisks(projectId)` | 实时反映最新风险 |
| 学生更新任务进度 | `refreshProjectRisks(projectId)` | 进度变化可能消除 R4 |
| 学生确认通知 | `refreshProjectRisks(projectId)` | 确认可能消除 R3 |
| 负责人创建/编辑任务 | `refreshProjectRisks(projectId)` | 新任务可能触发 R2 |
| 教师查看团队总览 | 批量 `refreshProjectRisks(projectIds)` | 刷新课程下所有活跃项目 |
| 教师查看风险预警页 | 批量 `refreshProjectRisks(projectIds)` | 同上 |

> **性能考虑**：SQLite 单文件，首版数据量 < 100 项目，每次全量计算耗时 < 100ms。若项目数增长，后续可在 Risk 表增加 `calculatedAt` 字段做缓存，5 分钟内不重复计算。

## 5. 风险通知

当 `refreshProjectRisks` 产生**新的 high 或 medium 风险**时，自动生成通知：

```typescript
// 在 refreshProjectRisks 的第 5 步之后
if (upserted.length > 0) {
  const highOrMedium = upserted.filter(r => r.level !== "low");
  if (highOrMedium.length > 0) {
    // 通知项目所有团队成员
    const members = await prisma.teamMember.findMany({
      where: { team: { project: { id: projectId } } },
    });
    for (const member of members) {
      await prisma.notification.create({
        data: {
          receiverId: member.userId,
          type: "risk",
          title: `项目出现 ${highOrMedium.length} 条新风险`,
          content: highOrMedium.map(r => `[${r.level}] ${r.reason}`).join("\n"),
          relatedTaskId: highOrMedium[0].relatedTaskId, // 首个关联任务
        },
      });
    }
  }
}
```

## 6. 数据模型

参见 `database-schema.md` 中 Risk 和 Notification 模型定义。

### Risk 表状态机

```
[新风险产生] → resolvedAt = null（活跃）
              ↓
[风险消除]   → resolvedAt = now()（已解决，保留记录）
```

风险消除条件：
- R1: task.status 变为 "done"
- R2: task.dueAt 被更新到更远的日期 或 task.status 变为 "done"
- R3: notification.confirmedAt 被设置
- R4: projectProgress >= 30%
- R5: 任意 task.updatedAt > 7 天前

以上条件通过 `refreshProjectRisks` 的"待清理"机制自动处理。

## 7. 可配置参数

所有阈值通过 `SystemConfig` 表管理，便于管理员调优：

| 配置 Key | 默认值 | 说明 |
|----------|--------|------|
| `risk.overdue.high.days` | `3` | R1 高风险逾期天数阈值 |
| `risk.overdue.medium.days` | `1` | R1 中风险逾期天数阈值 |
| `risk.deadline.hours` | `24` | R2 临近截止小时数 |
| `risk.unconfirmed.medium.hours` | `48` | R3 未确认中风险小时数 |
| `risk.unconfirmed.high.days` | `7` | R3 未确认高风险天数阈值 |
| `risk.low_progress.threshold` | `30` | R4 低进度百分比阈值 |
| `risk.silence.days` | `7` | R5 沉默天数阈值 |

实现时通过 `prisma.systemConfig.findMany()` 加载配置，缓存到内存中（修改后需刷新）。
