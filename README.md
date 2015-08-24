# wa-transaction-reports

#Installation
```
php composer.phar install
```
#Usage
Go into {projectdir}/public directory and run one of:

-Displays transaction reports for a merchant id-
```
php index.php transaction get --merchant={id}
```
-Imports data from csv into local database-
```
php index.php import --src={path}

```
