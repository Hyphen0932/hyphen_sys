# Hyphen System Docker 部署说明

## 1. 文件结构

- `docker-compose.yml`: 公共基础配置，只包含 `app` 和 `db`
- `docker-compose.dev.yml`: 开发环境覆盖，暴露开发端口并启用 phpMyAdmin
- `docker-compose.prod.yml`: 生产环境覆盖，只暴露主系统端口，不启动 phpMyAdmin
- `docker-compose.prod.admin.yml`: 生产环境临时维护覆盖，按需启用 phpMyAdmin
- `.env.dev`: 开发环境变量
- `.env.prod`: 生产环境变量
- `.env.dev.example`: 开发环境变量模板
- `.env.prod.example`: 生产环境变量模板
- `db/migrations/`: 数据库增量迁移目录
- `scripts/apply-migrations.ps1`: 执行待应用迁移
- `scripts/new-migration.ps1`: 创建迁移模板
- `scripts/deploy-dev.ps1`: 一键启动开发环境并执行迁移
- `scripts/deploy-prod.ps1`: 一键发布生产环境并执行迁移
- `scripts/backup-prod-db.ps1`: 一键备份生产数据库
- `RELEASE_SOP.md`: 标准发布流程

## 2. 开发环境

启动开发环境：

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy-dev.ps1
```

查看状态：

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml ps
```

查看日志：

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml logs -f
```

停止开发环境：

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml down
```

删除开发环境数据：

```powershell
docker compose -p hyphen_sys_dev --env-file .env.dev -f docker-compose.yml -f docker-compose.dev.yml down -v
```

开发环境访问地址：

- 主系统：`http://localhost:8080/hyphen_sys/`
- phpMyAdmin：`http://localhost:8081/`
- 数据库宿主机连接：`127.0.0.1:3307`

开发环境说明：

- 主系统、数据库和 phpMyAdmin 都只绑定到本机地址
- 适合本机开发、调试、看数据库
- phpMyAdmin 默认只在开发环境中启用

## 3. 生产环境

启动生产环境：

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy-prod.ps1
```

查看状态：

```powershell
docker compose -p hyphen_sys_prod --env-file .env.prod -f docker-compose.yml -f docker-compose.prod.yml ps
```

查看日志：

```powershell
docker compose -p hyphen_sys_prod --env-file .env.prod -f docker-compose.yml -f docker-compose.prod.yml logs -f
```

停止生产环境：

```powershell
docker compose -p hyphen_sys_prod --env-file .env.prod -f docker-compose.yml -f docker-compose.prod.yml down
```

删除生产环境数据：

```powershell
docker compose -p hyphen_sys_prod --env-file .env.prod -f docker-compose.yml -f docker-compose.prod.yml down -v
```

生产环境访问地址：

- 主系统：`http://localhost:8088/hyphen_sys/`

生产环境说明：

- 不启动 phpMyAdmin
- 不暴露数据库宿主机端口
- 更适合部署到服务器或给其他人访问
- 如果前面有 Nginx 或 Traefik，可以把 `APP_BIND_IP` 改成 `127.0.0.1`

临时启用生产 phpMyAdmin：

```powershell
docker compose -p hyphen_sys_prod --env-file .env.prod -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.prod.admin.yml up -d phpmyadmin
```

临时访问地址：

- phpMyAdmin：`http://localhost:8089/`

临时停止生产 phpMyAdmin：

```powershell
docker compose -p hyphen_sys_prod --env-file .env.prod -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.prod.admin.yml stop phpmyadmin
docker compose -p hyphen_sys_prod --env-file .env.prod -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.prod.admin.yml rm -f phpmyadmin
```

临时维护说明：

- 只有显式追加 `docker-compose.prod.admin.yml` 才会启动 phpMyAdmin
- 默认只绑定 `127.0.0.1:8089`
- 适合在服务器本机、远程桌面或 SSH 隧道场景下短时使用

## 4. phpMyAdmin 登录方式

开发环境和生产临时维护环境都使用同一套登录方式。

登录时填写：

- Server: `db`
- Username: `.env.dev` 中的 `MYSQL_USER`
- Password: `.env.dev` 中的 `MYSQL_PASSWORD`

或使用 root：

- Server: `db`
- Username: `root`
- Password: `.env.dev` 中的 `MYSQL_ROOT_PASSWORD`

如果是生产临时维护，则改为使用 `.env.prod` 里的数据库账号和密码。

## 5. 同时运行两个环境

可以同时运行开发环境和生产环境，因为：

- 命令里使用了不同的 project name：`hyphen_sys_dev` 和 `hyphen_sys_prod`
- 开发环境使用端口 `8080`、`8081`、`3307`
- 生产环境默认使用端口 `8088`
- 每个环境会生成各自独立的数据卷和网络

## 6. 首次切换建议

如果你之前是用单一 `docker-compose.yml` 启动的旧栈，建议先执行：

```powershell
docker compose down --remove-orphans
```

然后再按开发环境或生产环境的命令重新启动。

## 7. 安全建议

- 不要把 `.env.dev` 和 `.env.prod` 提交到 Git
- 生产环境不要直接开放数据库端口
- 生产环境不要长期启用 phpMyAdmin
- 部署到公网时，建议在反向代理层处理 HTTPS 和访问控制

## 8. 数据库迁移流程

新增字段、新表、索引或必须跟随版本上线的数据修复，不要直接修改旧 SQL dump，而是新增一个迁移文件到 [db/migrations/README.md](db/migrations/README.md) 所说明的目录。

创建迁移模板：

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\new-migration.ps1 -Name "add user last login"
```

预览开发环境待执行迁移：

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\apply-migrations.ps1 -Environment dev -DryRun
```

执行开发环境迁移：

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\apply-migrations.ps1 -Environment dev
```

执行生产环境迁移：

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\apply-migrations.ps1 -Environment prod
```

迁移执行记录会保存在 `hy_schema_migrations` 表中。

## 9. 发布 SOP

标准发布流程见 [RELEASE_SOP.md](RELEASE_SOP.md)。

快速命令：

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy-dev.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\deploy-prod.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\backup-prod-db.ps1 -Label "before_release"
```
