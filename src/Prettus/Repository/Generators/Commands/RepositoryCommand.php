<?php
namespace Prettus\Repository\Generators\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Prettus\Repository\Generators\FileAlreadyExistsException;
use Prettus\Repository\Generators\MigrationGenerator;
use Prettus\Repository\Generators\ModelGenerator;
use Prettus\Repository\Generators\RepositoryEloquentGenerator;
use Prettus\Repository\Generators\RepositoryInterfaceGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class RepositoryCommand
 * @package Prettus\Repository\Generators\Commands
 * @author Anderson Andrade <contato@andersonandra.de>
 */
class RepositoryCommand extends Command
{

    /**
     * The name of command.
     *
     * @var string
     */
    protected $name = 'make:repository';

    /**
     * The description of command.
     *
     * @var string
     */
    protected $description = 'Create a new repository.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Repository';

    /**
     * @var Collection
     */
    protected $generators = null;


    /**
     * Execute the command.
     *
     * @see fire()
     * @return void
     */
    public function handle(){
        $this->laravel->call([$this, 'fire'], func_get_args());
    }

    /**
     * Execute the command.
     *
     * @return void
     */
    public function fire()
    {
        $this->generators = new Collection();

        $modelGenerator = new ModelGenerator([
            'name'     => $this->argument('name'),
            'fillable' => $this->option('fillable'),
            'force'    => $this->option('force')
        ]);

        $modelName = $modelGenerator->getName();

        if ($this->confirm('Would you like to create a Model? [y|N]')) {
            $this->generators->push($modelGenerator);
        }

        if ($this->confirm('Would you like to create a Migration? [y|N]')) {
            $migrationGenerator = new MigrationGenerator([
                'name' => 'create_' . snake_case(str_plural($this->argument('name'))) . '_table',
                'fields' => $this->option('fillable'),
                'force' => $this->option('force'),
            ]);

            $this->generators->push($migrationGenerator);

            $this->call('make:factory', [
                'name' => $modelName . 'Factory',
                '-m' => 'Entities/' . $modelName,
            ]);

            $this->call('make:seeder', [
                'name' => $modelName . 'Seeder',
            ]);
        }

        $this->generators->push(new RepositoryInterfaceGenerator([
            'name'  => $modelName,
            'force' => $this->option('force'),
        ]));

        foreach ($this->generators as $generator) {
            $generator->run();
        }

        $model = $modelGenerator->getRootNamespace() . '\\' . $modelGenerator->getName();
        $model = str_replace([
            "\\",
            '/'
        ], '\\', $model);

        try {
            (new RepositoryEloquentGenerator([
                'name'      => $modelName,
                'rules'     => $this->option('rules'),
                'force'     => $this->option('force'),
                'model'     => $model
            ]))->run();
            $this->info("Repository created successfully.");
        } catch (FileAlreadyExistsException $e) {
            $this->error($this->type . ' already exists!');

            return false;
        }
    }


    /**
     * The array of command arguments.
     *
     * @return array
     */
    public function getArguments()
    {
        return [
            [
                'name',
                InputArgument::REQUIRED,
                'The name of class being generated.',
                null
            ],
        ];
    }


    /**
     * The array of command options.
     *
     * @return array
     */
    public function getOptions()
    {
        return [
            [
                'fillable',
                null,
                InputOption::VALUE_OPTIONAL,
                'The fillable attributes.',
                null
            ],
            [
                'rules',
                null,
                InputOption::VALUE_OPTIONAL,
                'The rules of validation attributes.',
                null
            ],
            [
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force the creation if file already exists.',
                null
            ],
        ];
    }
}
