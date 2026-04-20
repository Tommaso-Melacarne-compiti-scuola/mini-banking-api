<?php
class DbSingleton {
    private static object $instance;

    public static function getInstance() {
        if (!isset(self::$instance)) {
            self::$instance = new MySQLi('p:my_mariadb', 'root', 'ciccio', 'scuola');
        }
        
        return self::$instance;
    }
}