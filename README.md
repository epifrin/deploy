deploy
======
Simple deploys web-sites from local computer to ftp-server

### Description

### Structure

- deploy.php - main working script
- deploy.ini - configuration file which contains list of configuration files separate web-projects
- deploy_example.ini - example of configuration file web-site or web-project

## Russian

### Описание

Поддерживает деплой с локальной windows машины на FTP-север (не поддерживает sFTP).
Подойдет для разработчиков-одиночек, кто разработывает на локальной машине и выгружает на продашн сервер по ftp.

### Структура

- deploy.php - основной рабочий скрипт
- deploy.ini - файл конфигурации, который содержит список файлов конфигурации отдельных проектов
- deploy_example.ini - пример файла конфигурации веб-сайта или проекта

### Установка

1. Скопировать файлы deploy.php, deploy.ini, deploy_example.ini на локальный компьютер разработчика в любую папку доступную из web, чтобы можно было запустить скрипт deploy.php из браузера.
Важно! Не выкладывайте скрипт и ini-файлы на сервер, доступный из вне. В таком случае злоумышленник сможет увидеть параметры подключения. Либо защитите файлы от внешнего доступа.

2. Создать ini-файл по образцу deploy_example.ini для своего проекта. Если у вас есть несколько проектов или ваш проект очень большой, создайте несколько ini-файлов.

3. В deploy.ini заменить deploy_example.ini на ваш ini-файл. Если их несколько, то добавить.

4. Открыть в браузере deploy.php