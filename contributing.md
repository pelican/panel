# Contributing

Welcome to the Pelican project! We are excited to have you contribute to our open-source project. This guide will help you get started with setting up your development environment, understanding our coding standards, and making your first or next contribution.

Please also read our [code of conduct](./code_of_conduct.md).

## Getting started

To start contributing to Pelican Panel, you need to have a basic understanding of the following:

* [PHP](https://php.net) & [Laravel](https://laravel.com)
* [Livewire](https://laravel-livewire.com) & [Filament](https://filamentphp.com)
* [Git](https://git-scm.com) & [GitHub](https://github.com)

User guides live in the [Pelican documentation](https://pelican.dev/docs), not in this repository.

## Dev Environment Setup

The quickest way to get PHP and a webserver running locally is [Laravel Herd](https://herd.laravel.com) ([Windows](https://herd.laravel.com/windows) & [macOS](https://herd.laravel.com)). The (paid) Pro version adds easy MySQL and Redis hosting, but the free version with SQLite is fine for most cases. Any other PHP + webserver setup (e.g. Nginx or Apache) works too.

1. Fork the repository and clone your fork
2. Install PHP dependencies: `composer install`
3. Build the frontend assets: `npm install` and `npm run build` (use `npm run dev` while developing)
4. Configure your environment: `php artisan p:environment:setup`
5. Set up your database: `php artisan p:environment:database`, then run `php artisan migrate --seed --force`
6. Create your first admin user: `php artisan p:user:make`
7. Open the panel in your browser (via Herd's site URL or your webserver)

As IDE we recommend [Visual Studio Code](https://code.visualstudio.com) (free) or [PhpStorm](https://www.jetbrains.com/phpstorm) (paid).

## Coding Standards

We use PHPStan/ [Larastan](https://github.com/larastan/larastan) and [Pint](https://laravel.com/docs/pint) to enforce certain code styles and standards.  
You can run PHPStan via `composer phpstan` and Pint via `composer pint`.

See [code-standards.md](./code-standards.md) for the full rundown of tooling, tests, and codebase conventions.

## Making Contributions

From your forked repository, make your own changes on your own branch. (do not make changes directly to `main`!)  
When you are ready, you can submit a pull request to the Pelican repository. If you still work on your pull request or need help with something make sure to mark it as Draft.

Also, please make sure that your pull requests are as targeted and simple as possible and don't do a hundred things at a time. If you want to add/ change/ fix 5 different things you should make 5 different pull requests.

### Before opening a pull request

CI runs all of the following on every pull request, so save yourself a review round trip and run them locally first:

* `composer pint` (code style)
* `composer phpstan` (static analysis)
* `vendor/bin/pest` (tests; CI runs them against both SQLite and MySQL)

### Contributor License Agreement

All contributors must sign our [Contributor License Agreement](./contributor_license_agreement.md). The CLA Assistant bot will ask you to sign it on your first pull request; posting the comment it asks for completes the signature.

### Translations

If you add any new translation strings make sure to only add them to english.  
Other languages are translated via [Crowdin](https://crowdin.com/project/pelican-dev).

### API changes

The HTTP API is frozen for 1.0; see [api-versioning.md](./api-versioning.md) before changing anything a response returns or a request accepts.

## Code Review Process

Your pull request will then be reviewed by the maintainers.  
Once you have an approval from a maintainer, another will merge it once it’s confirmed.

Depending on the pull request size this process can take multiple days.

## Community and Support

* Help: [Discord](https://discord.gg/pelican-panel)
* Bugs: [GitHub Issues](https://github.com/pelican/panel/issues)
* Features: [GitHub Discussions](https://github.com/pelican/panel/discussions)
* Security vulnerabilities: See our [security policy](./security.md).
