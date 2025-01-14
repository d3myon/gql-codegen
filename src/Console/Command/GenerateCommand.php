<?php
declare(strict_types=1);

namespace GraphQLGenerator\Console\Command;

use GraphQLGenerator\Config\Config;
use GraphQLGenerator\Config\Endpoint;
use GraphQLGenerator\DefaultClassNamer;
use GraphQLGenerator\Generator\ClassGenerator;
use GraphQLGenerator\Processor;
use GraphQLGenerator\Psr4ClassDumper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class GenerateCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('generate');
        $this->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Configuration file', 'gql-codegen.xml');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = Config::fromXmlFile('gql-codegen.xml');

        foreach ($config->endpoints as $index => $endpoint) {
            $output->writeln(sprintf('<info>Processing endpoint #%d</info>', $index + 1));

            $this->processEndpoint($endpoint, $output);
        }

        return Command::SUCCESS;
    }

    private function processEndpoint(Endpoint $endpoint, OutputInterface $output): void
    {
        $classNamer  = new DefaultClassNamer($endpoint->target->namespacePrefix);
        $classDumper = new Psr4ClassDumper($endpoint->target->namespacePrefix, $endpoint->target->directory);

        $buildDefinition = (new Processor($classNamer))->process(
            $endpoint->schema->content(),
            $endpoint->types,
            $endpoint->resolvers
        );

        $classGenerator = ClassGenerator::forPhp80();

        foreach ($buildDefinition->inputTypes as $inputType) {
            $output->writeln(sprintf('- Generating %s', $inputType->className));
            $classDumper->dump($classGenerator->inputType($inputType));
        }

        foreach ($buildDefinition->resolvers as $resolver) {
            if ($resolver->args !== null) {
                $output->writeln(printf('- Generating %s', $resolver->args->className));
                $classDumper->dump($classGenerator->inputType($resolver->args));
            }

            $output->writeln(printf('- Generating %s', $resolver->className));
            $classDumper->dump($classGenerator->resolver($resolver));
        }

        $classDumper->dump($classGenerator->mainResolver($buildDefinition->mainResolver));
    }
}
