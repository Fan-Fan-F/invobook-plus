# 变更与改进计划 CHANGES — invobook-plus

本文档记录 `invobook-plus`（路线 A 二次开发）的改进计划、架构设计与落地实现。依据 `DIRECTION.md` 方向 brief 指引，聚焦中小企业发票与记账的核心痛点，围绕工程基线、金额计算精度、状态流转催缴与应收账龄分析四大维度展开实质性改造。

---

## 改进计划与实现说明

### 改进 1：工程基线稳定化与测试静态检查跑通 (Engineering Baseline & Quality Checks)
- **背景与问题**：原仓库经历重置后基线依赖要求高版本环境，测试套件与代码规范检查未完全连通，缺乏持续质量保障。
- **改进方案**：
  - 跑通完整工程基线（Laravel 13、PHP 8.5 / PHP 8.3 平台兼容、Pest 5、Larastan 3、Pint 1）。
  - 保留并修复原有的已有测试，确保 `php artisan test`、`vendor/bin/phpstan`、`vendor/bin/pint --test` 全部通过。
  - 为所有新增模块配套编写完整的 Pest 特性测试与单元测试，零跳过、零删除既有测试。

### 改进 2：精确金额计算引擎与多币种税率处理 (Accurate Integer-Cent Calculation & Multi-Currency Engine)
- **背景与问题**：中小企业财务发票的核心风险在于金额浮点数精度丢失，税率、百分比折扣、固定折扣与多行行项累加容易产生分角舍入误差。
- **改进方案**：
  - 核心金额全部采用整数“分”（Cents / Smallest Currency Unit）存储与运算，彻底规避浮点数计算误差。
  - 建立 `InvoiceCalculator` 金额计算服务：
    - 行项小计：`quantity * unit_price_cents`
    - 发票小计（Subtotal）：所有行项小计求和
    - 折扣计算（Discount）：支持固定金额扣减与百分比扣减（四舍五入到最近分），扣减上限不超过小计
    - 税费计算（Tax）：基于折后应税金额，依据发票指定税率（如 13%、6%、0% 等）精确计算税额
    - 发票总额（Total）：`subtotal_cents - discount_cents + tax_cents`
  - 提供多币种支持（USD, EUR, CNY, GBP, JPY），适配零小数位货币（如 JPY）与常规两位小数货币的格式化输出。

### 改进 3：发票状态机生命周期与自动逾期催缴命令 (Invoice Status Lifecycle & Overdue Reminder Automation)
- **背景与问题**：发票从开出到回款缺少清晰的状态流转机制，逾期款项需要人工逐一跟进，容易遗漏。
- **改进方案**：
  - 建立强类型的发票状态枚举 `InvoiceStatus`，涵盖生命周期状态：`draft`（草稿）→ `sent`（已发送）→ `partially_paid`（部分支付）→ `paid`（已付清）/ `overdue`（已逾期）/ `cancelled`（已作废）。
  - 实现状态流转验证，禁止非法越级流转（如已作废不可继续付款）。
  - 实现 Artisan 调度命令 `invoices:check-overdue`：
    - 自动扫描到期日已过且未全额支付的有效发票，将状态标记为 `overdue`。
    - 触发 `InvoiceOverdueMail` 邮件通知，记录催缴时间戳 `overdue_reminder_sent_at`。
    - 支持测试环境 `Mail::fake` 验证。

### 改进 4：多笔收款记录与客户应收账龄分析报表 (Multi-Payment Tracking & AR Aging Analysis Report)
- **背景与问题**：实际商业场景中一张发票常分批到账（预付款、进度款、尾款），原系统缺少账款回收跟踪；且管理层缺乏按客户维度的应收账款账龄分析（AR Aging Report）。
- **改进方案**：
  - 新增收款模型 `Payment`，支持单张发票关联多笔独立收款记录（包含金额、支付方式、参考号、支付日期与备注）。
  - 收款动作 `RecordPaymentAction`：登记收款后自动累加已付金额 `paid_cents`，根据未结清余额自动更新发票状态为 `partially_paid` 或 `paid`，并校验防止超额支付。
  - 账龄分析报表服务 `AgingReportService`：
    - 按业界标准账龄区间统计：`current`（未到期）、`1-30 days`（逾期 1-30 天）、`31-60 days`（逾期 31-60 天）、`61-90 days`（逾期 61-90 天）、`90+ days`（逾期 90 天以上）。
    - 按客户（Client）聚合汇总各区间未收金额与笔数，并计算全盘应收汇总。
    - 全程采用预加载（Eager Loading）与批量聚合查询，杜绝 N+1 查询隐患。

---

## 自测记录 (Self-Test Log)

### 1. 运行环境与版本
- **PHP 版本**：`PHP 8.5.12 (cli) (built: Feb 20 2026 09:21:50) (NTS)`
  - *说明*：项目 `composer.json` 声明约束为 `php: ^8.5`。通过官方打包源安装 PHP 8.5 CLI 核心与扩展环境，严格避免使用可能阻塞的 `add-apt-repository`。
- **框架与工具版本**：Laravel 13.x, Pest 5.x, PHPUnit 13.x, Larastan 3.x, Pint 1.x, Node v24.22.1, Bun 1.4.4.

### 2. 数据库迁移与配置
- 执行命令：`php artisan migrate --force`
- 执行结果：增量迁移文件按序顺利执行：
  - `2026_11_17_000001_create_clients_table`
  - `2026_11_17_000002_create_invoices_table`
  - `2026_11_17_000003_create_invoice_items_table`
  - `2026_11_17_000004_create_payments_table`
  - 既有 starter kit 基础迁移（users, cache, jobs 等）保持不变。

### 3. 代码规范与静态检查
- **Laravel Pint 风格格式化**：
  - 执行命令：`vendor/bin/pint --format agent`
  - 检查结果：全部通过（`{"tool":"pint","result":"passed"}`），零代码风格冲突。
- **PHPStan / Larastan 静态类型检查**：
  - 执行命令：`vendor/bin/phpstan analyse`
  - 检查结果：全部通过（`{"tool":"phpstan","result":"passed","errors":0}`），零类型与架构违规。

### 4. 自动化测试套件 (Pest & PHPUnit)
- 执行命令：`php artisan test` / `vendor/bin/pest`
- 测试统计：**46 passed, 0 failed, 197 assertions** (耗时 ~9.4s)
- 测试覆盖模块：
  - `tests/Unit/Services/InvoiceCalculatorTest.php`：行项计算、单项汇总、百分比/固定折扣边界、税率与折后税额计算、零小数币种（JPY）与常规币种格式化。
  - `tests/Unit/Actions/TransitionInvoiceStatusActionTest.php`：状态机合法跃迁、非法流转拦截、部分支付与全额支付幂等性校验。
  - `tests/Unit/ArchTest.php`：架构预设严格模式（`strict`、`laravel`）、代码结构约束全部通过。
  - `tests/Feature/InvoiceManagementTest.php`：发票创建、行项关联、单张详情查询、按客户/状态过滤列表。
  - `tests/Feature/PaymentRecordingTest.php`：多笔款项登记、累加计算、状态流转（sent → partially_paid → paid）、超额付款拦截。
  - `tests/Feature/OverdueInvoiceReminderTest.php`：到期判定、`invoices:check-overdue` Artisan 命令执行、逾期状态标记与 `InvoiceOverdueMail` 队列邮件发放校验。
  - `tests/Feature/AgingReportTest.php`：未到期、1-30天、31-60天、61-90天、90+天应收账龄桶统计与客户聚合、无 N+1 查询隐患验证。
  - 原有既有测试（`tests/Feature/WelcomeTest.php`, `tests/Unit/BootstrapTest.php`, `tests/Unit/UserTest.php`）：全部完好保留并持续通过。

### 5. 核心业务流程与命令验证
- **逾期检查命令**：`php artisan invoices:check-overdue`
  - 执行结果：扫描数据库中逾期账单并输出 `No overdue invoices found.`（或在发现逾期账单时标记并发出催缴）。
- **路由注册检查**：`php artisan route:list`
  - 发票接口（`GET /api/invoices`, `POST /api/invoices`, `GET /api/invoices/{invoice}`, `POST /api/invoices/{invoice}/send`）
  - 收款接口（`POST /api/invoices/{invoice}/payments`）
  - 账龄分析接口（`GET /api/reports/aging`）
  全部路由注册正常。
- **前端资源编译**：`bun run build`（`vp build`）
  - 成功编译输出前端资产与 `public/build/manifest.json`，确保应用就绪启动。
