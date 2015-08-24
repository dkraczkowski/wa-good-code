<?php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;use Zend\Console\Prompt;

class ApplicationController extends AbstractActionController {
    public function importAction(){
        $file_src=$this->getRequest()->getParam('src');
        $filename = getcwd() .DIRECTORY_SEPARATOR . $file_src;

        if (!is_readable($filename)) {
            throw new \Exception('Import File "' . $filename . '" not found or not readable.');
        }
        $confirmPrompt = new Prompt\Confirm('Are you sure you want to import data. Old data will be erased? [Y] for yes, [N] for no - ');

        $proceed = $confirmPrompt->show();
        //user did not allow console application for data import
        if (!$proceed) {
            return 'Canceled by user.' . PHP_EOL;
        }

        $delimiterPrompt = new Prompt\Char('Please input string delimiter - ');
        $delimiterPrompt->setAllowedChars(';-,.');
        $delimiter = $delimiterPrompt->show();

        $parsed = $this->parseCsvFile($filename, $delimiter);
        if ($parsed === false)
        {
            return 'CSV file is corrupted.' . PHP_EOL;
        }
        $model = $this->serviceLocator->get('Application\Model\ApplicationModel');
        $model->importData($parsed);
        return 'Success.' . PHP_EOL;

    }

    public function parseCsvFile($file_name, $delimiter)
    {
        $header = null;
        $data = array();
        if (($handle = fopen($file_name, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                if(!$header) {
                    $header = $row;
                    if (!$this->validateHeader($header)) {
                        return false;
                    }
                } else {
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }
        return $data;
    }

    public function validateHeader($header){
        $intersect = array_intersect(array('merchant', 'date', 'value'), $header);
        if (count($intersect) != 3) {//missing headers
            return false;
        }
        return true;
    }

    public function getTransactionsReportAction() {

        $merchantId = $this->getRequest()->getParam('merchant');
        $model = $this->serviceLocator->get('Application\Model\ApplicationModel');
        $data = $model->getReport($merchantId);
        $output = "";
        if ($data === false) {
            return 'No data found for merchant with id: ' . $merchantId . '.' . PHP_EOL;
        }

        $output .= PHP_EOL . 'Report for merchant: ' . $merchantId . PHP_EOL;
        $total = 0;
        foreach ($data as $day => $amount) {
            $output .= "\t" . $day . "  => " . number_format($amount, 2) . " GBP" . PHP_EOL;
            $total .= $amount;
        }
        $output .= str_repeat('-', 38) . PHP_EOL;
        $output .= str_repeat(' ', 20) . 'Total: ' . number_format($total, 2) . " GBP" . PHP_EOL;

        return $output;
    }
}
