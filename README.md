# invobook-plus

> 面向中小企业的现代化发票与应收账款管理系统，具备高精度金额核算、多币种税率处理、全生命周期状态流转与账龄分析报表能力。
> Modern invoicing and accounts receivable management system for small and medium businesses, featuring high-precision financial calculations, multi-currency & tax handling, lifecycle status tracking, and AR aging analytics.

---

## 中文文档

### 项目简介

`invobook-plus` 是基于开源发票应用 `invobook` 进行的二次开发增强版本（Route A）。针对中小企业财务管理中易出现的金额舍入精度丢失、多币种与税费计算混乱、逾期账款难以追踪、应收账款账龄不清晰等核心痛点，进行了彻底的架构级增强与功能补强。

### 核心功能与改进点

本项目对应 [CHANGES.md](CHANGES.md) 中的 4 项实质性改进：

1. **工程基线稳定化与质量保障 (Engineering Baseline & Quality Checks)**
   - 适配最新 Laravel 13 与 PHP 8.5/8.3 环境，严格遵守 Laravel 开发规范。
   - 完备的静态检查与代码风格治理（Larastan 3 / PHPStan 零错误，Laravel Pint 零格式冲突）。
   - 完整的自动化测试套件（Pest 5 / PHPUnit 13，共 46 个测试用例，197 个断言，包含严格的架构测试 `ArchTest`）。

2. **精确金额计算引擎与多币种税率处理 (Accurate Integer-Cent Calculation & Multi-Currency Engine)**
   - 全面废除浮点数金额运算，金额全字段以最小货币单位“分”（Cents）存储，杜绝舍入累积误差。
   - 内置 `InvoiceCalculator` 金额引擎：
     - 行项金额：`quantity * unit_price_cents`
     - 发票小计（Subtotal）与两类折扣支持（百分比折扣、固定金额折扣，支持边界扣减防护）
     - 税额计算（Tax）：基于折后金额按税率（如 13%、6%、0% 等）精确计算
     - 发票总额（Total）：`subtotal_cents - discount_cents + tax_cents`
   - 多币种支持（USD, EUR, CNY, GBP, JPY 等），自适应处理常规两位小数币种与零小数位币种（如 JPY）的格式化。

3. **发票全生命周期状态流转与逾期催缴 (Invoice Status Lifecycle & Overdue Reminder Automation)**
   - 强类型状态机 `InvoiceStatus`：`draft`（草稿）→ `sent`（已发出）→ `partially_paid`（部分付款）→ `paid`（已付清）/ `overdue`（已逾期）/ `cancelled`（已作废）。
   - 严格的状态迁移校验（`TransitionInvoiceStatusAction`），拦截非法跃迁（如已作废订单禁止操作支付）。
   - 自动化逾期检查任务 `php artisan invoices:check-overdue`：自动判定过期未结清账单，变更为 `overdue` 状态并触发排队发送邮件通知 `InvoiceOverdueMail`。

4. **多笔收款记录与客户应收账龄报表 (Multi-Payment Tracking & AR Aging Analysis Report)**
   - 细粒度收款追踪：单张发票支持多次分批付款（订金、进度款、尾款），自动累加已付金额并校验避免超额支付。
   - 客户应收账龄分析引擎（`AgingReportService` / `GET /api/reports/aging`）：
     - 业界标准 5 档账龄桶划分：未到期（`current`）、1-30天、31-60天、61-90天、90天以上。
     - 按客户维度自动聚合未收账款金额与笔数，并输出全盘应收账款汇总。
     - 采用预加载与优化关联查询，彻底消除 N+1 性能隐患。

### 快速开始

#### 环境要求
- PHP >= 8.3（推荐 PHP 8.5）
- SQLite 3 / MySQL 8.0+ / PostgreSQL 14+
- Composer >= 2.0
- Node.js >= 20.x 或 Bun >= 1.0

#### 安装步骤

1. **获取代码并进入目录**
   ```bash
   git clone https://github.com/your-username/invobook-plus.git
   cd invobook-plus
   ```

2. **安装 PHP 依赖**
   ```bash
   composer install
   ```

3. **配置环境变量**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **运行数据库迁移**
   ```bash
   php artisan migrate
   ```

5. **编译前端静态资源**
   ```bash
   bun install && bun run build
   # 或使用 npm
   # npm install && npm run build
   ```

6. **启动开发服务器**
   ```bash
   php artisan serve
   ```
   应用默认运行在 `http://127.0.0.1:8000`。

#### 核心命令与测试
- 运行完整测试套件：
  ```bash
  php artisan test
  ```
- 运行代码规范检查与格式化：
  ```bash
  vendor/bin/pint --test
  ```
- 运行静态类型检查：
  ```bash
  vendor/bin/phpstan analyse
  ```
- 手动执行逾期账单扫描与催缴通知：
  ```bash
  php artisan invoices:check-overdue
  ```

### 来源与致谢

本项目基于 [invobook](https://github.com/Hasnayeen/invobook) 二次开发，原项目由 Hasnayeen 以 MIT 许可证开源。

### 许可证

本项目沿用 MIT 许可证。原项目仓库未包含独立 LICENSE 文件，许可证以其 `composer.json` 中的 `"license": "MIT"` 声明为准。二次开发在仓库根目录补放标准 MIT 许可证全文，版权归原作者 Hasnayeen 所有，详情参见根目录 [LICENSE](LICENSE) 文件。

---

## English Documentation

### Overview

`invobook-plus` is an enhanced secondary development (Route A) of the open-source invoicing application `invobook`. It addresses key pain points in SME financial management—including floating-point rounding errors, complex multi-currency tax rules, lack of overdue tracking, and missing accounts receivable aging reports—through architecture-level improvements and domain-driven design.

### Key Features & Improvements

This project delivers 4 substantive improvements corresponding to [CHANGES.md](CHANGES.md):

1. **Engineering Baseline & Quality Assurance**
   - Compatible with Laravel 13 and PHP 8.5/8.3 with strict adherence to modern conventions.
   - Zero static analysis warnings (`phpstan` Level 3 clean) and strict Pint code formatting.
   - Comprehensive test suite (Pest 5 / PHPUnit 13, 46 passing tests with 197 assertions, including architectural guardrails `ArchTest`).

2. **Accurate Integer-Cent Calculation & Multi-Currency Engine**
   - Eliminates floating-point calculation errors by storing all financial figures in integer cents (smallest currency unit).
   - Dedicated `InvoiceCalculator` service:
     - Item subtotal: `quantity * unit_price_cents`
     - Invoice subtotal and flexible discounts (percentage or fixed amount, with subtotal boundary bounds)
     - Tax calculation based on discounted taxable amount at configurable rates (e.g., 13%, 6%, 0%)
     - Total: `subtotal_cents - discount_cents + tax_cents`
   - Multi-currency support (USD, EUR, CNY, GBP, JPY), seamlessly formatting both 2-decimal currencies and zero-decimal currencies (such as JPY).

3. **Invoice Status Lifecycle & Automated Overdue Reminders**
   - Robust state machine `InvoiceStatus`: `draft` → `sent` → `partially_paid` → `paid` / `overdue` / `cancelled`.
   - Transition validation (`TransitionInvoiceStatusAction`) preventing invalid state jumps (e.g., recording payment against a cancelled invoice).
   - Automated Artisan command `php artisan invoices:check-overdue`: automatically identifies past-due invoices, transitions status to `overdue`, and queues `InvoiceOverdueMail` reminder emails.

4. **Multi-Payment Tracking & Accounts Receivable Aging Report**
   - Granular payment tracking: multiple installments (deposits, progress payments, final balances) per invoice with overpayment prevention.
   - Accounts Receivable Aging Engine (`AgingReportService` / `GET /api/reports/aging`):
     - Standard 5-bucket aging distribution: `current`, `1-30 days`, `31-60 days`, `61-90 days`, `90+ days`.
     - Grouped summary per client as well as total portfolio receivables.
     - Eager loading and aggregated queries ensuring zero N+1 query overhead.

### Quick Start

#### Requirements
- PHP >= 8.3 (PHP 8.5 recommended)
- SQLite 3 / MySQL 8.0+ / PostgreSQL 14+
- Composer >= 2.0
- Node.js >= 20.x or Bun >= 1.0

#### Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/invobook-plus.git
   cd invobook-plus
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Configure Environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Run Database Migrations**
   ```bash
   php artisan migrate
   ```

5. **Build Frontend Assets**
   ```bash
   bun install && bun run build
   # or with npm
   # npm install && npm run build
   ```

6. **Start Application**
   ```bash
   php artisan serve
   ```
   Access the application at `http://127.0.0.1:8000`.

#### Commands & Verification
- Run tests:
  ```bash
  php artisan test
  ```
- Run code style verification:
  ```bash
  vendor/bin/pint --test
  ```
- Run static analysis:
  ```bash
  vendor/bin/phpstan analyse
  ```
- Run overdue invoice scan:
  ```bash
  php artisan invoices:check-overdue
  ```

### Source & Acknowledgments

This project is a secondary development based on [invobook](https://github.com/Hasnayeen/invobook), originally open-sourced by Hasnayeen under the MIT License.

### License

This project inherits the MIT License. The original repository did not include a standalone `LICENSE` file; licensing is based on the `"license": "MIT"` declaration in its `composer.json`. A complete standard MIT license file has been placed in the repository root with copyright attributed to the original author Hasnayeen. See [LICENSE](LICENSE) for details.
