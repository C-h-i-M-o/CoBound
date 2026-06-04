# 协界（CoBound）

协界（CoBound）是一款面向高校学生的课程项目协作管理平台，目标是解决小组作业、课程设计和竞赛项目中常见的组队困难、沟通混乱、任务责任不清、进度缺乏监督等问题。

## 项目定位
- 面向学生：支持按课程和兴趣组队、创建项目、分配任务、确认通知、跟踪进度和查看风险。
- 面向教师：支持查看课程项目过程、团队进度、风险预警和成员贡献度参考。
- 面向管理员：支持用户、课程基础数据、系统配置和通知模板管理。

## 首轮产出
本仓库首轮采用“SRS主文档牵引”的需求驱动原型迭代流程，先完成需求规格和支撑文档，再进入系统开发。

核心文档位于 `docs/`：
1. 项目选题说明
2. 可行性分析报告
3. 用户画像分析
4. 业务流程图
5. 用例图
6. 用例规约
7. 功能需求说明
8. 非功能需求说明
9. 数据流图
10. 数据字典
11. 界面原型
12. 软件需求规格说明书
13. 测试用例与验收表

## 技术规划
- 前端与后端：Next.js 全栈
- 语言：TypeScript
- 数据层：SQLite + Prisma
- 平台形态：响应式Web端
- 扩展方向：未来可通过复用API接入微信小程序

## 目录结构
```text
.
├── AGENTS.md
├── README.md
├── .gitignore
└── docs/
    ├── 01-topic-selection.md
    ├── 02-feasibility-analysis.md
    ├── 05-personas.md
    ├── 06-business-process.md
    ├── 07-use-case-diagram.md
    ├── 08-use-case-spec.md
    ├── 09-functional-requirements.md
    ├── 10-non-functional-requirements.md
    ├── 11-data-flow-diagram.md
    ├── 12-data-dictionary.md
    ├── 13-wireframes.md
    ├── 14-srs.md
    └── 15-test-cases-acceptance.md
```

## 开发原则
- 需求先行：所有功能必须能追溯到角色画像、业务流程、用例规约或SRS。
- 原型迭代：先通过静态线框图确认核心流程，再进行代码开发。
- 简洁实现：首版只做通知确认，不做实时聊天；贡献度为过程参考分，教师可调整。
