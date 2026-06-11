# 贡献度算法详细设计

## 1. 设计原则

- **可解释性优先**：参考分的每个子项都可追溯到具体数据（哪几个任务完成了、哪几次延期、哪几条通知已确认）
- **参考非裁决**：系统提供参考分，教师拥有最终裁量权
- **可调整可追溯**：教师调整必须填写理由，所有调整记录永久保留
- **数据不足不强行打分**：当过程数据不足以支撑计算时，标记为"参考不足"
- **实时更新**：任务状态变更、通知确认时，自动重算关联成员的贡献度

## 2. 计算公式

```
参考分 = 基础分(60) + 完成率分(0–20) + 响应率分(0–10) + 阶段分(0–10) - 延期扣分(0–20)

最终分数 = clamp(参考分, 0, 100)
```

### 2.1 分项说明

| 分项 | 满分 | 数据来源 | 计算方式 |
|------|------|---------|---------|
| 基础分 | 60 | — | 固定值，确保即使0任务完成也有底线分 |
| 完成率分 | 20 | Task 表 `assigneeId` + `status` | `(doneTasks / totalTasks) × 20` |
| 响应率分 | 10 | Notification 表 `receiverId` + `confirmedAt` | `(confirmedNotifications / totalNotifications) × 10` |
| 阶段分 | 10 | ContributionRecord `stageScore` | 教师手动设定，默认 0 |
| 延期扣分 | -20 (上限) | Task 表 `status == overdue` | `min(overdueCount × 5, 20)` |

### 2.2 示例计算

**场景：学生张同学，参与了项目"校园二手交易平台"**

```
总任务数: 5
已完成: 3 → 完成率 = 3/5 = 0.6 → 完成率分 = 0.6 × 20 = 12
总通知数: 8
已确认: 7 → 响应率 = 7/8 = 0.875 → 响应率分 = 0.875 × 10 = 8.75
延期次数: 2 → 延期扣分 = 2 × 5 = 10
阶段分: 教师已给 7

参考分 = 60 + 12 + 8.75 + 7 - 10 = 77.75 → 取整 78
```

## 3. 核心实现

```typescript
// src/lib/contribution-calc.ts

interface ContributionMetrics {
  totalTasks: number;
  doneTasks: number;
  overdueCount: number;
  totalNotifications: number;
  confirmedNotifications: number;
  stageScore: number;
}

async function recalcContribution(
  userId: string,
  projectId: string
): Promise<ContributionRecord> {
  // 1. 收集指标数据
  const metrics = await collectMetrics(userId, projectId);

  // 2. 判断数据是否充足
  if (metrics.totalTasks === 0) {
    // 保存标记为"参考不足"的记录（referenceScore 留 0）
    return upsertRecord(userId, projectId, metrics, null);
  }

  // 3. 计算各项分数
  const completionScore = (metrics.doneTasks / metrics.totalTasks) * 20;
  const responseScore = metrics.totalNotifications > 0
    ? (metrics.confirmedNotifications / metrics.totalNotifications) * 10
    : 0;
  const delayPenalty = Math.min(metrics.overdueCount * 5, 20);
  const stageScore = metrics.stageScore; // 0-10，教师设定

  // 4. 计算参考分
  const referenceScore = Math.max(0, Math.min(100,
    60 + completionScore + responseScore + stageScore - delayPenalty
  ));
  const rounded = Math.round(referenceScore);

  // 5. 持久化（adjustedScore 保持不变）
  return upsertRecord(userId, projectId, metrics, rounded);
}

async function collectMetrics(
  userId: string,
  projectId: string
): Promise<ContributionMetrics> {
  // 任务指标
  const tasks = await prisma.task.findMany({
    where: { projectId, assigneeId: userId },
  });
  const totalTasks = tasks.length;
  const doneTasks = tasks.filter(t => t.status === "done").length;
  const overdueCount = tasks.filter(t => t.status === "overdue").length;

  // 通知指标（仅统计 task/deadline 类型）
  const notifications = await prisma.notification.findMany({
    where: {
      receiverId: userId,
      type: { in: ["task", "deadline"] },
      relatedTaskId: { in: tasks.map(t => t.id) },
    },
  });
  const totalNotifications = notifications.length;
  const confirmedNotifications = notifications.filter(n => n.confirmedAt !== null).length;

  // 阶段分（从已有记录读取）
  const existing = await prisma.contributionRecord.findUnique({
    where: { userId_projectId: { userId, projectId } },
  });
  const stageScore = existing?.stageScore ?? 0;

  return { totalTasks, doneTasks, overdueCount, totalNotifications, confirmedNotifications, stageScore };
}

async function upsertRecord(
  userId: string,
  projectId: string,
  metrics: ContributionMetrics,
  referenceScore: number | null,  // null = 参考不足
): Promise<ContributionRecord> {
  return prisma.contributionRecord.upsert({
    where: { userId_projectId: { userId, projectId } },
    create: {
      userId,
      projectId,
      taskCompletionRate: metrics.totalTasks > 0 ? metrics.doneTasks / metrics.totalTasks : 0,
      delayCount: metrics.overdueCount,
      confirmResponseRate: metrics.totalNotifications > 0
        ? metrics.confirmedNotifications / metrics.totalNotifications
        : 0,
      stageScore: metrics.stageScore,
      referenceScore: referenceScore ?? 0,
    },
    update: {
      taskCompletionRate: metrics.totalTasks > 0 ? metrics.doneTasks / metrics.totalTasks : 0,
      delayCount: metrics.overdueCount,
      confirmResponseRate: metrics.totalNotifications > 0
        ? metrics.confirmedNotifications / metrics.totalNotifications
        : 0,
      stageScore: metrics.stageScore,
      referenceScore: referenceScore ?? 0,
      // 注意：adjustedScore 不在此更新，仅通过 adjustContribution 接口变更
    },
  });
}
```

## 4. 教师调分机制

### 4.1 调整接口

```typescript
// src/lib/contribution-calc.ts

async function adjustContribution(
  projectId: string,
  userId: string,
  newScore: number,
  reason: string,
  adjustedBy: string,  // 教师 ID
): Promise<ContributionRecord> {
  // 1. 查找现有记录
  const record = await prisma.contributionRecord.findUnique({
    where: { userId_projectId: { userId, projectId } },
  });
  if (!record) throw new NotFoundError("贡献度记录不存在");

  // 2. 校验 reason 不为空
  if (!reason || reason.trim().length === 0) {
    throw new ValidationError("调整理由不能为空");
  }

  // 3. 校验分数范围
  if (newScore < 0 || newScore > 100) {
    throw new ValidationError("分数必须在 0-100 之间");
  }

  // 4. 创建调整记录
  await prisma.contributionAdjustment.create({
    data: {
      recordId: record.id,
      adjustedBy,
      oldScore: record.adjustedScore ?? record.referenceScore,
      newScore,
      reason: reason.trim(),
    },
  });

  // 5. 更新 adjustedScore（不覆盖 referenceScore）
  return prisma.contributionRecord.update({
    where: { id: record.id },
    data: { adjustedScore: newScore },
  });
}
```

### 4.2 分数展示逻辑

前端展示时：

```typescript
// 若 adjustedScore 不为 null → 展示 adjustedScore（标注"教师调整"）
// 若 adjustedScore 为 null 且 referenceScore 有意义 → 展示 referenceScore（标注"系统参考"）
// 若 referenceScore == 0 且 totalTasks == 0 → 展示"数据不足"
```

### 4.3 调整历史

每条调整记录包含完整信息供审计：

| 字段 | 说明 |
|------|------|
| 调整教师 | 谁调的 |
| 调整时间 | 什么时候调的 |
| 旧分数 | 调整前（adjustedScore 或 referenceScore） |
| 新分数 | 调整后 |
| 调整理由 | 为什么调（必填） |

## 5. 数据来源与计算时机

### 5.1 数据来源映射

| 指标 | Task 表字段 | Notification 表字段 |
|------|------------|---------------------|
| completedTasks | `status == "done"` | — |
| overdueCount | `status == "overdue"` | — |
| confirmedNotifications | — | `confirmedAt != null` |
| stageScore | — | — （手动输入） |

### 5.2 触发时机

| 事件 | 触发方式 | 影响范围 |
|------|---------|---------|
| 教师访问贡献度页面 | `recalcContribution(userId, projectId)` — 对所有成员 | 整个项目 |
| 学生更新任务进度 → done | `recalcContribution(assigneeId, projectId)` | 该成员 |
| 任务被标记为 overdue | `recalcContribution(assigneeId, projectId)` | 该成员 |
| 学生确认通知 | `recalcContribution(userId, projectId)` | 该成员 |
| 教师调整贡献度 | `adjustContribution()` — 不触发重算 | 仅更新 adjustedScore |
| 教师修改阶段分 | `recalcContribution(userId, projectId)` | 该成员 |

## 6. 参考不足判定

```typescript
function isDataInsufficient(metrics: ContributionMetrics): boolean {
  return metrics.totalTasks === 0;
}
```

当 `totalTasks == 0` 时：
- `referenceScore` 设为 0
- 前端展示 "数据不足，无法生成参考分"
- `adjustedScore` 仍可被教师设置（教师可以基于其他观察打分）

## 7. 贡献度记录结构

```typescript
{
  userId: "cuid_xxx",
  projectId: "cuid_yyy",
  // 明细指标（可解释性）
  taskCompletionRate: 0.75,       // 75% 完成率
  delayCount: 2,                  // 延期 2 次
  confirmResponseRate: 0.90,      // 90% 确认率
  stageScore: 8,                  // 教师阶段打分 8/10

  // 分数
  referenceScore: 78,             // 系统参考分
  // 计算过程: 60 + 15(完成率) + 9(响应率) + 8(阶段) - 10(延期) = 82 → 但实际 calculcated as 78

  adjustedScore: null,            // 教师未调整
}
```

### 可解释性输出（前端展示用）

```
参考分 78 分 的构成：
  • 基础分：60 分
  • 任务完成率 75%（3/5 已完成）：+15 分
  • 通知确认率 87.5%（7/8 已确认）：+8.75 分
  • 教师阶段评分：+7 分
  • 延期 2 次：-10 分
  ────────────────
  合计：80.75 → 取整 78 分

（教师已调整为 85 分，理由："该同学在关键模块贡献较大，且主动帮助队友解决技术难题"）
```

## 8. 边界情况

| 场景 | 处理方式 |
|------|---------|
| 成员未被分配任何任务 | 参考不足，不计算 |
| 成员没有收到任何通知 | 响应率分 = 0 |
| 成员所有任务都已完成 | 延期扣分 = 0，完成率分 = 20 |
| 成员大量延期（> 4 次） | 延期扣分上限 -20 |
| 计算结果为负数 | clamp 到 0 |
| 计算结果超过 100 | clamp 到 100 |
| 教师未设置阶段分 | stageScore = 0 |
| 教师调整后系统又触发重算 | adjustedScore 不受影响，持续保留 |
