# Contributing

Welcome to the Pelican project! We are excited to have you contribute to our open-source project. This guide will help you get started with setting up your development environment, understanding our coding standards, and making your first or next contribution.

## Getting started

To start contributing to Pelican Panel, you need to have a basic understanding of the following:

* [PHP](https://php.net) & [Laravel](https://laravel.com)
* [Livewire](https://laravel-livewire.com) & [Filament](https://filamentphp.com)
* [Git](https://git-scm.com) & [GitHub](https://github.com)

## Dev Environment Setup

1. Fork the Repository
2. Clone your Fork
3. Install Dependencies (PHP modules & composer, and run `composer install`)
4. Configure your Environment (via `php artisan p:environment:setup`)
5. Set up your Database (via `php artisan p:environment:database`) and run Migrations (via `php artisan migrate --seed --force`)
6. Create your first Admin User (via `php artisan p:user:make`)
7. Start your Webserver (e.g. Nginx or Apache)

As IDE we recommend [Visual Studio](https://visualstudio.microsoft.com)/ [Visual Studio Code](https://code.visualstudio.com) (free) or [PhpStorm](https://www.jetbrains.com/phpstorm) (paid).

To easily install PHP and the Webserver we recommend Laravel Herd. ([Windows](https://herd.laravel.com/windows) & [macOS](https://herd.laravel.com))  
The (paid) Pro version of Laravel Herd also offers easy MySQL and Redis hosting, but the free version is fine for most cases.

## Coding Standards

We use PHPStan/ [Larastan](https://github.com/larastan/larastan) and PHP-CS-Fixer/ [Pint](https://laravel.com/docs/12.x/pint) to enforce certain code styles and standards.  
You can run PHPStan via `\vendor\bin\phpstan analyse` and Pint via `\vendor\bin\pint`.

## Making Contributions

From your forked repository, make your own changes on your own branch. (do not make changes directly to `main`!)  
When you are ready, you can submit a pull request to the Pelican repository. If you still work on your pull request or need help with something make sure to mark it as Draft.

Also, please make sure that your pull requests are as targeted and simple as possible and don't do a hundred things at a time. If you want to add/ change/ fix 5 different things you should make 5 different pull requests.

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
