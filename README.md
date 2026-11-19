# GitLab Tools

A Laravel team workspace for tools built around a shared GitLab project. The first tool builds historical milestone burndown charts from GitLab issues.

## Setup

Requirements: PHP 8.3+, Composer, and MySQL 8+.

```bash
cp .env.example .env
composer install
php artisan key:generate
```

Create a MySQL database, then set `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`. Configure the shared GitLab project in the same file:

```dotenv
GITLAB_URL=https://gitlab.com
GITLAB_PROJECT=group/project
GITLAB_TOKEN=your_token_with_read_api_scope
```

Run the schema and application:

```bash
php artisan migrate
npm install
composer run dev
```

Open `http://127.0.0.1:8000` and sign in. The GitLab credentials are server-side environment configuration and are never shown to users.

## Team authentication

Registration, login, logout, password hashing, session rotation, and login rate limiting are included. All authenticated users share the configured GitLab project tools. Public registration is enabled by default; after your project members have created accounts, set `ALLOW_REGISTRATION=false` in `.env` and run `php artisan config:clear` to close registration.

`npm run dev` starts only the Vite asset server. Use `composer run dev` to start Laravel, Vite, and the other local development processes together. Alternatively, run `php artisan serve` and `npm run dev` in separate terminals.

## Security and data

- The GitLab token is read from the server environment and is never rendered into a page or stored in the database.
- Use HTTPS in production, protect the app with authentication or network access controls, and keep `APP_KEY` stable. Changing it makes stored tokens unreadable.
- Self-managed GitLab instances are supported by changing the URL in the connection form.

## How the chart is calculated

For every date from milestone start through due date, an issue counts as remaining when it had already been created but had not yet been closed. Issues added after the start appear as scope growth. Future dates are left blank. The ideal line starts from the scope known on the milestone start date.

Milestones must have both a start date and due date. Issue weight mode treats unweighted issues as zero points.

## Tests

```bash
php artisan test
```
