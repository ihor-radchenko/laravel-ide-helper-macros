<?php

namespace IhorRadchenko\LaravelIdeHelperMacros\Console;

use Barryvdh\Reflection\DocBlock;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use IhorRadchenko\LaravelIdeHelperMacros\PackageTag;

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
        \Illuminate\Database\Schema\Blueprint::class,
        \Illuminate\Support\Arr::class,
        \Illuminate\Support\Carbon::class,
        \Illuminate\Support\Collection::class,
        \Illuminate\Console\Scheduling\Event::class,
        \Illuminate\Filesystem\Filesystem::class,
        \Illuminate\Mail\Mailer::class,
        \Illuminate\Routing\Redirector::class,
        \Illuminate\Database\Eloquent\Relations\Relation::class,
        \Illuminate\Cache\Repository::class,
        \Illuminate\Routing\ResponseFactory::class,
        \Illuminate\Routing\Route::class,
        \Illuminate\Routing\Router::class,
        \Illuminate\Validation\Rule::class,
        \Illuminate\Support\Str::class,
        \Illuminate\Translation\Translator::class,
        \Illuminate\Routing\UrlGenerator::class,
        \Illuminate\Database\Query\Builder::class,
        \Illuminate\Database\Eloquent\Builder::class,
        \Illuminate\Http\JsonResponse::class,
        \Illuminate\Http\RedirectResponse::class,
        \Illuminate\Auth\RequestGuard::class,
        \Illuminate\Http\Response::class,
        \Illuminate\Auth\SessionGuard::class,
        \Illuminate\Http\UploadedFile::class,
        \Illuminate\Support\Stringable::class,
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
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            if (!$reflection->hasProperty('macros')) {
                continue;
            }

            $originalDoc = $reflection->getDocComment();
            $fileName = $reflection->getFileName();
            $className = $reflection->getShortName();

            if (!$fileName) {
                continue;
            }

            $property = $reflection->getProperty('macros');
            $property->setAccessible(true);
            $macros = $property->getValue();
            $phpDoc = new DocBlock($reflection, new DocBlock\Context($reflection->getNamespaceName()));
            $phpDoc->setText($class);

            if (!$macros) {
                if ($originalDoc && $phpDoc->hasTag('package')) {
                    foreach ($phpDoc->getTagsByName('package') as $tag) {
                        if ($tag instanceof PackageTag && $tag->getContent() === 'ide_helper_macros') {
                            $contents = $this->files->get($fileName);
                            $contents = $this->strReplaceFirst($originalDoc, '', $contents);
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

            $serializer = new DocBlock\Serializer();
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
                $defaultValue = $parameter->isArray() ? '[]' : ($parameter->getDefaultValue() ?? 'null');
                $parameterString .= " = {$defaultValue}";
            }
        }

        return $parameterString;
    }

    protected function strReplaceFirst($needle, $replace, $haystack): array|string
    {
        $pos = strpos($haystack, $needle);
        if ($pos !== false) {
            return substr_replace($haystack, $replace, $pos, strlen($needle));
        }
        return $haystack;
    }
}
