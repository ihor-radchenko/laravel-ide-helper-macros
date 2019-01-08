#Laravel IDE Macros

It is advised to be used with [Laravel IDE Helper](https://github.com/barryvdh/laravel-ide-helper), which generates helper files for your IDE, so it'll be able to highlight and understand some Laravel-specific syntax.
This package can generate phpDocs for Laravel classes, based on Macroable trait.

##Installation

Require this package with composer using the following command:

```
composer require --dev ihor-radchenko/laravel-ide-helper-macros
```

If you are using Laravel 5.4 or lower, you must register the `IdeHelperMacrosServiceProvider` manually.

## Configuration
Run the following command to publish the configuration file to `config/ide-helper-macros.php`:
```
php artisan vendor:publish --provider="IhorRadchenko\LaravelIdeHelperMacros\IdeHelperMacrosServiceProvider"
```

##Automatic phpDoc generation for Laravel Macroable classes

You need add macro or mixin in some service provider, example:
```php
/**
 * Bootstrap services.
 *
 * @return void
 */
public function boot(): void
{
    /**
     * @param array $data
     *
     * @return \Illuminate\Http\Response
     */
    \Illuminate\Http\Response::macro('addContent', function (array $data) {
        /** @var \Illuminate\Http\Response $this */
        $response = $this;
        $content = json_decode($response->getContent(), true);
        if (is_array($content)) {
            $response->setContent(json_encode(array_merge($content, $data)));
        }

        return $response;
    });
}
```
 
And run the following command to generate the phpDocs IDE helpers:
```
php artisan ide-helper:macros
```

After that the following block will be generated in the \Illuminate\Http\Response class: 
```php
/**
 * Illuminate\Http\Response
 *
 * @method \Illuminate\Http\Response addContent(array $data)
 * @package ide_helper_macros
 */
class Response extends BaseResponse
{
```