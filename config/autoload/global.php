<?php
libxml_use_internal_errors(true);
date_default_timezone_set('Europe/Warsaw');
return array(
    'service_manager' => array(
        'factories' => array(
            'db' => function() {
                return new \Zend\Db\Adapter\Adapter(array(
                    'driver' => 'Pdo_Sqlite',
                    'database' => __DIR__ . '/../../data/db.db'
                ));
            },
            'cache' => function() {
                return \Zend\Cache\StorageFactory::factory(array(
                    'adapter' => array(
                        'name'    => 'memory',
                        'options' => array('ttl' => 3600),
                    ),
                    'plugins' => array(
                        'exception_handler' => array('throw_exceptions' => false),
                    ),
                ));
            }
        )
    )
);