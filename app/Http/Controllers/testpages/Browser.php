<?php

namespace App\Http\Controllers\testpages;

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;

trait Browser
{
    /**
     * @method  browser -> Método para instanciar o chromeDriver
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    public function browser()
    {

        //verifica o modo, se for produção executa no modo silencioso
        if (config('app.env') === 'production') {
            //silent mode
            $args = [
                '--disable-gpu',
                '--headless',
                '--no-sandbox',
                'window-size=1460,820'
            ];
        } else {
            //Mostra o browser na tela
            $args = [
                //'no-first-run',
                'window-size=1460,820'
            ];
        }
        $host = 'http://localhost:4444/';
        $desiredCapabilities = DesiredCapabilities::chrome();

        return RemoteWebDriver::create($host, $desiredCapabilities);
    }


    /**
     * @method  browserSession -> Método para recuperar o Driver
     * @return \Facebook\WebDriver\Remote\RemoteWebDriver
     */
    public function browserSession($session)
    {
        $host = 'http://localhost:4444/';

        return RemoteWebDriver::createBySessionID($session, $host, 60000, 60000);
    }
}