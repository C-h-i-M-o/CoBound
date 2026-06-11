# CoBound 二次开发说明

## 技术基线

- 上游版本：Leantime 3.8.0
- PHP：8.2 及以上
- Node.js：仓库锁文件对应版本
- 数据库：MySQL 8.4
- 开发端口：`5080`

为降低升级风险，二次开发只修改用户可见层，不重命名 `Leantime\...` 命名空间、不修改数据库表名和既有 API。

## 标准开发流程

在仓库根目录执行：

```bash
make clean build
make run-dev
```

访问 `http://localhost:5080`。

首次构建需要下载 Composer、npm 和 Docker 依赖。前端资源由 Laravel Mix 生成到 `public/dist`。

## 关键目录

- `app/Language/zh-CN.ini`：简体中文语言包。
- `app/Domain/Auth/Templates`：登录、邀请注册与密码页面。
- `app/Domain/Help`：新用户默认项目、检查清单和页面导览。
- `public/assets/images`：CoBound Logo、PNG 和 favicon 源资源。
- `.docker/docker-compose.production.yml`：生产编排。

## 邀请流程

创建用户时，系统写入状态 `i` 和唯一 `pwReset` 令牌，不调用 Mailer。邀请路由保持为：

```text
/auth/userInvite/{token}
```

有效旧邀请链接仍可继续使用。底层邮件类和 SMTP 环境变量保留，仅默认关闭并隐藏入口。

## 验证清单

```bash
make clean build
make run-dev
```

然后检查：

1. 页面标题、导航、登录页和 favicon 均为 CoBound。
2. 新建用户后跳转至详情页，可复制邀请链接。
3. 邀请链接可完成五步简体中文注册。
4. 忘记密码页面不生成令牌、不发送邮件。
5. 桌面端和移动端的工作台、项目、看板、里程碑及目标导览为中文。
