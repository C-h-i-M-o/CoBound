# 数据字典

## 用户 User
| 字段 | 类型 | 说明 |
| --- | --- | --- |
| id | string | 用户唯一标识 |
| name | string | 姓名 |
| role | enum | student、teacher、admin |
| email | string | 邮箱或登录账号 |
| createdAt | datetime | 创建时间 |

## 课程 Course
| 字段 | 类型 | 说明 |
| --- | --- | --- |
| id | string | 课程唯一标识 |
| name | string | 课程名称 |
| semester | string | 学期 |
| teacherId | string | 负责教师 |

## 项目 Project
| 字段 | 类型 | 说明 |
| --- | --- | --- |
| id | string | 项目唯一标识 |
| courseId | string | 所属课程 |
| teamId | string | 所属团队 |
| title | string | 项目名称 |
| status | enum | preparing、active、archived |

## 团队 Team
| 字段 | 类型 | 说明 |
| --- | --- | --- |
| id | string | 团队唯一标识 |
| name | string | 团队名称 |
| leaderId | string | 负责人 |
| maxMembers | number | 最大成员数 |

## 任务 Task
| 字段 | 类型 | 说明 |
| --- | --- | --- |
| id | string | 任务唯一标识 |
| projectId | string | 所属项目 |
| assigneeId | string | 负责人 |
| title | string | 标题 |
| dueAt | datetime | 截止时间 |
| status | enum | todo、doing、done、overdue |
| progress | number | 完成百分比 |

## 通知 Notification
| 字段 | 类型 | 说明 |
| --- | --- | --- |
| id | string | 通知唯一标识 |
| receiverId | string | 接收人 |
| type | enum | task、deadline、risk、system |
| title | string | 通知标题 |
| confirmedAt | datetime | 确认时间，可为空 |

## 风险 Risk
| 字段 | 类型 | 说明 |
| --- | --- | --- |
| id | string | 风险唯一标识 |
| projectId | string | 所属项目 |
| level | enum | low、medium、high |
| reason | string | 风险原因 |
| relatedTaskId | string | 关联任务，可为空 |

