<?php
namespace Qm\Command;

use \Symfony\Component\Console\Command\Command;
use \Symfony\Component\Console\Input\InputArgument;
use \Symfony\Component\Console\Input\InputInterface;
use \Symfony\Component\Console\Output\OutputInterface;

use \Qm\Schema;

class DropTableCommand extends Command
{
    protected function configure()
    {
        $this->setName('dropTable');
        $this->setDescription('Execute drop table for specified class');
        $this->addArgument('class', InputArgument::REQUIRED, 'Class name');
        $this->addArgument('dumpSql', InputArgument::OPTIONAL, 'Dump SQL query');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getArgument('dumpSql') == 'true') {
            echo Schema::getDropTable($input->getArgument('class'));
        } else {
            Schema::dropTable($input->getArgument('class'));
        }
        return true;
    }
}
