# Student Marketplace and Auction Platform

A full-stack PHP and MySQL marketplace designed for students to buy, sell, and auction used items securely.

This project was independently developed by **Ifeanyichukwu Samuel Udeokoli** as a Higher National Diploma Software and Web Development project at Federal Polytechnic Bida.

## Highlights

- Multi-role authentication (Buyer, Seller, Administrator)
- Fixed-price, Auction, and Hybrid listings
- Real-time bidding workflows
- Flutterwave and Paystack payment integration
- Order tracking and dispute resolution
- Messaging and notifications
- PHP, MySQL, JavaScript, HTML, and CSS

## Features

### Accounts and authentication
- Buyer, seller, and administrator account types
- User registration, login, and logout
- Email verification and password recovery flows
- User profile and seller profile management
- Role-oriented dashboards and account pages

### Listings and product discovery
- Create, edit, and manage marketplace listings
- Product categories and listing images
- Listing search and browsing endpoints
- Listing moderation and reporting tools for administrators
- Buyer watchlist functionality

### Auctions and bidding
- Auction and hybrid listing types
- Starting bids, bid increments, and reserve prices
- Live bid and bid-history endpoints
- Proxy/automatic bidding support in the auction logic
- Auction status checks and scheduled auction processing
- Buy-now support for eligible fixed-price and hybrid listings

### Buying, orders, and payments
- Shopping cart and checkout pages
- Order creation, order history, and order tracking
- Order status management
- Payment workflow code for Flutterwave and Paystack
- Pay-on-delivery configuration
- Payment callback and verification endpoints
- Transaction and payment records

> Payment credentials are not included in this repository. Payment providers must be configured with your own test credentials before testing. Do not use live credentials in a development environment.

### Communication and customer support
- Buyer and seller messaging
- Inbox and conversation pages
- Notifications and read-status handling
- Email templates for account and marketplace events
- Review and seller-rating functionality
- Dispute management pages and administrator dispute tools

### Administration
- Administrator dashboard
- User and seller management
- Category and listing administration
- Listing moderation
- Order and transaction management
- Dispute handling
- Reports, settings, fees, and administrative logs

## Technology

- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Asynchronous requests:** AJAX
- **Database access:** PHP PDO
- **Payment integrations:** Flutterwave and Paystack code
- **Email:** PHPMailer library and SMTP configuration
- **PDF generation:** FPDF library

## Project structure

The main application is organised into the following areas:

```text
marketplace/
├── admin/             # Administrator pages and management tools
├── api/               # Application endpoints used by the frontend
├── assets/            # CSS, JavaScript, images, and uploaded assets
├── auctions/          # Auction detail and bidding pages
├── auth/              # Registration, login, verification, and recovery
├── buyer/             # Buyer dashboard and shopping workflows
├── cart/              # Cart and checkout pages
├── classes/           # Core application classes
├── config/            # Database, application, mail, and gateway configuration
├── cron/              # Scheduled maintenance and auction tasks
├── dashboard/         # Role-based dashboard pages
├── database/          # Earlier database export
├── email-templates/   # HTML email templates
├── includes/          # Shared page components and helpers
├── lib/               # Bundled third-party libraries
├── messaging/         # Buyer/seller conversations
├── notifications/     # Notification pages and endpoints
├── orders/            # Order history and tracking workflows
├── payments/          # Payment initialization and verification handlers
├── products/          # Product and category pages
├── reviews/           # Review functionality
├── seller/            # Seller dashboard and listing/order tools
├── sql/               # Current application schema and SQL scripts
├── index.php          # Application entry page
└── .htaccess          # Apache configuration
```

## Requirements

Use a local PHP/MySQL environment such as XAMPP, WAMP, or a comparable Apache, PHP, and MySQL stack.

The application requires:
- Apache with PHP enabled
- MySQL or a compatible MariaDB server
- PHP PDO and the PDO MySQL driver
- PHP cURL for the payment and external API requests
- PHP file upload support
- A browser with JavaScript enabled

The bundled email library and PDF library are included in the project. SMTP credentials are needed only if you want to test outgoing email.

## Local installation

### 1. Get the project

Clone the repository after it has been published:

```bash
git clone https://github.com/YOUR-GITHUB-USERNAME/YOUR-REPOSITORY-NAME.git
```

Or download the repository ZIP and extract it.

### 2. Place it in your web server directory

For XAMPP, place the project folder inside:

```text
C:\xampp\htdocs\
```

The resulting path should look like:

```text
C:\xampp\htdocs\marketplace\
```

### 3. Create the database

The current application configuration defaults to a database named `marketplace_db`.

Create that database in phpMyAdmin or MySQL. The current schema file is:

```text
sql/schema.sql
```

Import `sql/schema.sql` into the database you created.

**Schema note:** The archive also contains `database/marketplace.sql`. It represents an older database structure and uses a different database name. For the current application setup, use `sql/schema.sql` unless the project code and database schema have first been deliberately reconciled.

### 4. Configure environment variables

The application reads environment variables from a `.env` file in the project root through `config/constants.php`.

Create a local `.env` file in the same directory as `index.php`. Do not commit this file to GitHub.

Example configuration (replace the blank values with your own local/test credentials):

```dotenv
DB_HOST=localhost
DB_NAME=marketplace_db
DB_USER=root
DB_PASS=

SITE_URL=http://localhost/marketplace
SITE_NAME=Student Marketplace
ADMIN_EMAIL=admin@example.com

SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_FROM=no-reply@example.com

FLUTTERWAVE_PUBLIC_KEY=
FLUTTERWAVE_SECRET_KEY=
FLUTTERWAVE_ENCRYPTION_KEY=
FLUTTERWAVE_SECRET_HASH=
FLUTTERWAVE_ENVIRONMENT=test

PAYSTACK_PUBLIC_KEY=
PAYSTACK_SECRET_KEY=
PAYSTACK_ENVIRONMENT=test

ENABLE_PAY_ON_DELIVERY=true
ENABLE_FLUTTERWAVE=true
ENABLE_PAYSTACK=true

SALT=replace-with-a-long-random-secret
SESSION_TIMEOUT=1800
```

Keep all secret values private. Use test-mode credentials while developing. Never commit `.env`, `.env1`, copied environment files, passwords, API tokens, or production credentials.

The project currently contains environment loading and database defaults in `config/constants.php`. Review those defaults before deploying to a public server, and ensure production secrets are supplied through a secure environment configuration.

### 5. Start the application

In the XAMPP Control Panel, start Apache and MySQL.

Open:

```text
http://localhost/marketplace/
```

If you use a different folder name, port, or virtual host, update `SITE_URL` in your local `.env` accordingly.

## Payment and email setup

### Flutterwave and Paystack

The codebase contains payment initialization and verification handlers for Flutterwave and Paystack. To test them:

1. Create or use your own provider test account.
2. Obtain test-mode credentials from the provider dashboard.
3. Add the credentials to your local `.env` file.
4. Keep the provider environment set to `test`.
5. Configure the provider callback/webhook URLs for your local testing method or deployed test environment.
6. Test payment initialization, callback handling, and server-side verification before relying on the workflow.

A successful redirect alone is not proof that a payment is valid. Payment status should be confirmed by the server through the provider's verification mechanism.

### Email

Configure SMTP details in the local `.env` file if you want to test email verification, password recovery, and marketplace email notifications. Do not publish SMTP usernames, app passwords, or tokens.

## Security and repository hygiene

Before making the repository public:

- Keep `.env` and all credential files out of version control.
- Do not commit API keys, SMTP passwords, database passwords, private tokens, or application salts.
- Exclude logs, debug scripts, temporary output, backups, and local archives.
- Review SQL seed files to ensure they contain only fictional demonstration data.
- Do not publish real customer records, uploaded identity documents, or private user content.
- Remove or disable test-only account-management and debugging scripts from any deployed environment.
- Change all default or demonstration passwords before deployment.
- Configure production error handling so database errors and stack traces are not displayed to visitors.
- Use HTTPS and secure server configuration for a public deployment.
- Rotate any credential that has ever been committed to a public repository.

A `.gitignore` file helps prevent accidental additions, but it does not remove files that Git is already tracking. If a secret was committed previously, remove it from the repository history and rotate the exposed credential.

## Development notes and limitations

This README describes the structure and functionality visible in the supplied project archive. It is not a claim that every feature has been independently security-audited or tested in a fresh installation.

Before production deployment, the application should receive a complete review of authentication, authorization, file uploads, payment verification, database schema consistency, error handling, and scheduled tasks. Configure and test cron jobs in the target hosting environment if you intend to use the scheduled auction and maintenance scripts.

## Author

**Ifeanyichukwu Samuel Udeokoli**

Software and Web Development

GitHub: [Ubasinachukwu](https://github.com/Ubasinachukwu)

LinkedIn: [Ifeanyichukwu Samuel Udeokoli](https://www.linkedin.com/in/ifeanyichukwu-samuel-udeokoli-02bb33397)

## License

No explicit project license was identified in the supplied archive. Unless a license is added, standard copyright applies and reuse or redistribution permissions should not be assumed.
