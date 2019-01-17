<?php

namespace IhorRadchenko\LaravelIdeHelperMacros\Console;

use Barryvdh\Reflection\DocBlock;
use IhorRadchenko\LaravelIdeHelperMacros\PackageTag;
use Illuminate\Auth\RequestGuard;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\Repository;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\FactoryBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Console\PresetCommand;
use Illuminate\Foundation\Testing\TestResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailer;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\ResponseFactory;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Rule;

class IdeHelperMacros extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ide-helper:macros';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate phpDoc for Macroable class.';

    /**
     * @var array
     */
    protected $classes = [
        Blueprint::class,
        Arr::class,
        Carbon::class,
        Collection::class,
        Event::class,
        FactoryBuilder::class,
        Filesystem::class,
        Mailer::class,
        PresetCommand::class,
        Redirector::class,
        Relation::class,
        Repository::class,
        ResponseFactory::class,
        Route::class,
        Router::class,
        Rule::class,
        Str::class,
        TestResponse::class,
        Translator::class,
        UrlGenerator::class,
        Builder::class,
        JsonResponse::class,
        RedirectResponse::class,
        RequestGuard::class,
        Response::class,
        SessionGuard::class,
        UploadedFile::class,
    ];

    /**
     * @var Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     *
     * @param Filesystem $files
     *
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \ReflectionException|FileNotFoundException
     */
    public function handle()
    {
        $classes = array_merge($this->classes, config('ide-helper.macroable', []));

        foreach ($classes as $class) {
            if (! class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            if (! $reflection->hasProperty('macros')) {
                continue;
            }

            $originalDoc = $reflection->getDocComment();
            $fileName = $reflection->getFileName();
            $className = $reflection->getShortName();
            if (! $fileName) {
                continue;
            }

            $property = $reflection->getProperty('macros');
            $property->setAccessible(true);
            $macros = $property->getValue();
            $phpDoc = new DocBlock($reflection, new DocBlock\Context($reflection->getNamespaceName()));
            $phpDoc->setText($class);

            if (! $macros) {
                if ($originalDoc && $phpDoc->hasTag('package')) {
                    foreach ($phpDoc->getTagsByName('package') as $tag) {
                        if ($tag instanceof PackageTag && $tag->getContent() === 'ide_helper_macros') {
                            $contents = $this->files->get($fileName);
                            $contents = str_replace_first($originalDoc, '', $contents);
                            $this->files->put($fileName, $contents);
                            $this->info('Remove phpDocBlock from ' . $fileName);

                            continue;
                        }
                    }
                }
                continue;
            }

            foreach ($phpDoc->getTags() as $tag) {
                $phpDoc->deleteTag($tag);
            }

            foreach ($macros as $macroName => $macroCallback) {
                $macro = new \ReflectionFunction($macroCallback);
                $params = array_map(function (\ReflectionParameter $parameter) {
                    return $this->prepareParameter($parameter);
                }, $macro->getParameters());
                $params = implode(', ', $params);
                $doc = $macro->getDocComment();
                $returnType = $doc && preg_match('/@return ([a-zA-Z\[\]\|\\\]+)/', $doc, $matches) ? $matches[1] : '';
                $tag = DocBlock\Tag::createInstance("@method {$returnType} {$macroName}({$params})", $phpDoc);
                $phpDoc->appendTag($tag);
            }
            $phpDoc->appendTag(DocBlock\Tag::createInstance('@package ide_helper_macros'));

            $serializer = new DocBlock\Serializer;
            $serializer->getDocComment($phpDoc);
            $docComment = $serializer->getDocComment($phpDoc);
            $contents = $this->files->get($fileName);

            if ($originalDoc) {
                $contents = str_replace($originalDoc, $docComment, $contents);
            } else {
                $needle = "class {$className}";
                $replace = "{$docComment}\nclass {$className}";
                $pos = strpos($contents, $needle);
                if ($pos !== false) {
                    $contents = substr_replace($contents, $replace, $pos, strlen($needle));
                }
            }
            if ($this->files->put($fileName, $contents)) {
                $this->info('Written new phpDocBlock to ' . $fileName);
            }
        }
    }

    /**
     * @param \ReflectionParameter $parameter
     *
     * @return string
     */
    protected function prepareParameter(\ReflectionParameter $parameter): string
    {
        $parameterString = trim(optional($parameter->getType())->getName() . ' $' . $parameter->getName());

        if ($parameter->isOptional()) {
            if ($parameter->isVariadic()) {
                $parameterString = '...' . $parameterString;
            } else {
                $defaultValue = $parameter->isArray() ? '[]' : $parameter->getDefaultValue();
                $parameterString .= " = {$defaultValue}";
            }
        }

        return $parameterString;
    }
}
