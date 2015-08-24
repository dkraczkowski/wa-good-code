<?php
use Zend\Http\Client as HttpClient;
return array(
    'controllers' => array(
        'invokables' => array(
            'Application\Controller\Application' => 'Application\Controller\ApplicationController',
        )
    ),
    'console' => array(
        'router' => array(
            'routes' => array(
                'get-report' => array(
                    'options' => array(
                        'route'    => 'transaction get --merchant=',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Application',
                            'action'     => 'getTransactionsReport'
                        )
                    )
                ),
                'import-data' => array(
                    'options' => array(
                        'route'    => 'import --src=',
                        'defaults' => array(
                            'controller' => 'Application\Controller\Application',
                            'action'     => 'import'
                        )
                    )
                )
            )
        )
    ),
    'service_manager' => array(
        'factories' => array(
            'Application\Model\ApplicationModel' => function ($sm) {
                $model = new \Application\Model\ApplicationModel();
                $model->setDB($sm->get('db'));
                $model->setCurrencyExchangeService($sm->get('Application\Model\CurrencyExchange'));
                return $model;
            },
            'Application\Model\CurrencyExchange' => function ($sm) {
                $model = new \Application\Model\CurrencyExchange($sm->get('cache'));
                $model->setHttpClient(new HttpClient());
                return $model;
            },

        )
    )
);
