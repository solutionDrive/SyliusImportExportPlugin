<?php

declare(strict_types=1);

namespace FriendsOfSylius\SyliusImportExportPlugin\Command;

use FriendsOfSylius\SyliusImportExportPlugin\Importer\ImporterInterface;
use FriendsOfSylius\SyliusImportExportPlugin\Importer\ImporterRegistry;
use Sylius\Component\Registry\ServiceRegistry;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ImportDataCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setName('sylius:import')
            ->setDescription('Import a file.')
            ->setDefinition([
                new InputArgument('importer', InputArgument::OPTIONAL, 'The importer to use.'),
                new InputArgument('file', InputArgument::OPTIONAL, 'The file to import.'),
                // @TODO try to guess the format from the file to make this optional
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The format of the file to import'),
                new InputOption('details', null, InputOption::VALUE_NONE, 'If to return details about skipped/failed rows'),
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var ServiceRegistry $registry */
        $registry = $this->getContainer()->get('sylius.importers_registry');

        $importer = $input->getArgument('importer');
        if (empty($importer)) {
            $this->listImporters($input, $output, $registry);

            return 0;
        }

        $format = $input->getOption('format');

        $name = ImporterRegistry::buildServiceName($importer, $format);
        if (!$registry->has($name)) {
            $message = sprintf(
                "<error>There is no '%s' importer.</error>",
                $name
            );
            $output->writeln($message);

            $this->listImporters($input, $output, $registry);

            return 1;
        }

        $file = $input->getArgument('file');

        /** @var ImporterInterface $service */
        $service = $registry->get($name);
        $result = $service->import($file);

        $message = sprintf(
            "<info>Imported '%s' via the %s importer</info>",
            $file,
            $name
        );
        $output->writeln($message);

        $io = new SymfonyStyle($input, $output);

        $details = $input->getOption('details');
        if ($details) {
            $imported = implode(', ', $result->getSuccessRows());
            $skipped = implode(', ', $result->getSkippedRows());
            $failed = implode(', ', $result->getFailedRows());
            $countOrRows = 'rows';
        } else {
            $imported = count($result->getSuccessRows());
            $skipped = count($result->getSkippedRows());
            $failed = count($result->getFailedRows());
            $countOrRows = 'count';
        }

        $io->listing(
            [
                sprintf('Time taken: %s ms ', $result->getDuration()),
                sprintf('Imported %s: %s', $countOrRows, $imported),
                sprintf('Skipped %s: %s', $countOrRows, $skipped),
                sprintf('Failed %s: %s', $countOrRows, $failed),
            ]
        );

        return 0;
    }

    private function listImporters(InputInterface $input, OutputInterface $output, ServiceRegistry $registry): void
    {
        $output->writeln('<info>Available importers:</info>');
        $all = array_keys($registry->all());
        $importers = [];
        foreach ($all as $importer) {
            $importer = explode('.', $importer);
            $importers[$importer[0]][] = $importer[1];
        }

        $list = [];
        foreach ($importers as $importer => $formats) {
            $list[] = sprintf(
                '%s (formats: %s)',
                $importer,
                implode(', ', $formats)
            );
        }

        $io = new SymfonyStyle($input, $output);
        $io->listing($list);
    }
}
