<?php

namespace App\Http\Controllers\testpages;

use App\Http\Controllers\testpages\Browser;
use Facebook\WebDriver\WebDriverBy as By;
use Facebook\WebDriver\WebDriverSelect as Select;
use Facebook\WebDriver\WebDriverExpectedCondition as Condition;
use Facebook\WebDriver\Remote\LocalFileDetector as FileDetector;

class Testpages
{
    use Browser;

    private $driver;
    private $clientSite = [
        "table" => "https://testpages.herokuapp.com/styled/tag/table.html",
        "form" => "https://testpages.herokuapp.com/styled/basic-html-form-test.html",
        "download" => "https://testpages.herokuapp.com/styled/download/download.html",
        "upload" => "https://testpages.herokuapp.com/styled/file-upload-test.html"
    ];

    /**
     * Recupera as informações da tabela.
     *
     * @param array $dados Os dados a serem inseridos na tabela.
     * @return Client - modelo do registro criado.
     */
    public function getDataTable()
    {
        try {

            $this->driver = $this->browser();

            $this->driver->get($this->clientSite['table']);

            //recupera a quantidade de linha da tabela
            $trs = count($this->driver->findElements(By::xpath("//*[@id='mytable']/tbody/tr")));

            //se houver mais que uma linha, verifica a quantidade de colunas
            if($trs > 1) {
                $tds = count($this->driver->findElements(By::xpath("//*[@id='mytable']/tbody/tr[2]/td")));
            }

            $arrClient = [];
            //loop nas linhas para pegar as informações
            //inicia a contagem no 2, porque a primeira linha é cabeçalho
            for ($countRows=2; $countRows <= $trs; $countRows++) {
                
                $rowContent = [];

                //loop nas colunas
                for ($countCols=1; $countCols <= $tds; $countCols++) {
                    $content = $this->driver->findElement(By::xpath("//*[@id='mytable']/tbody/tr[$countRows]/td[$countCols]"))->getText();
                    array_push($rowContent, $content);
                }

                array_push($arrClient, $rowContent);
            }

            //encerra sessão
            $this->driver->quit();

            //retorna array com as infos da tabela
            return $arrClient;

        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * Testa o preenchimento e envio do form teste.
     *
     * Esse método navega até a página do formulário, preenche os campos com dados de teste,
     * submete o formulário e verifica se a página de sucesso foi exibida corretamente.
     *
     * @return bool - Retorna true se o formulário foi preenchido e submetido.
     */
    public function fillForm()
    {

        try {
            $this->driver = $this->browser();

            $this->driver->get($this->clientSite['form']);

            // Preenche o campo username
            $usernameField = $this->driver->findElement(By::name('username'));
            $usernameField->sendKeys('jonsnow');

            // Preenche o campo password
            $passwordField = $this->driver->findElement(By::name('password'));
            $passwordField->sendKeys('DaenerysTargaryen');

            // Preenche o campo comments
            $commentsField = $this->driver->findElement(By::name('comments'));
            $commentsField->clear(); // Limpa o campo antes de adicionar o novo conteúdo
            $commentsField->sendKeys('... my watch is ended!');

            // Desmarca o checkbox com o valor cb2 que vem selecionado por padrão
            $checkboxField = $this->driver->findElement(By::cssSelector('input[value="cb3"]'));
            $checkboxField->click();

            // Seleciona o checkbox com o valor cb1
            $checkboxField = $this->driver->findElement(By::cssSelector('input[value="cb1"]'));
            $checkboxField->click();

            // Seleciona o radio com o valor rd2
            $radioField = $this->driver->findElement(By::cssSelector('input[value="rd2"]'));
            $radioField->click();

            // localiza o elemento multiple select
            $multipleField = $this->driver->findElement(By::name('multipleselect[]'));
            $multipleSelect = new Select($multipleField); // cria um objeto multipleField

            $values = array('ms1', 'ms2'); // valores dos itens a serem selecionados

            // seleciona cada item pelo valor
            foreach ($values as $value) {
                $multipleSelect->selectByValue($value);
            }

            // Localiza o elemento select pelo nome
            $selectField = $this->driver->findElement(By::name('dropdown'));
            $select = new Select($selectField); // Cria um objeto selectField
            $select->selectByValue('dd5'); // Seleciona a opção com valor 'option2'

            // Realiza o upload de um arquivo para o form
            $this->__uploadFile($this->driver, "filename", public_path('imgs/drogon.JPG'));

            // Submete o formulário
            $submitBtn = $this->driver->findElement(By::cssSelector('input[type="submit"]'));
            $submitBtn->click();

            // Verifica se a página de sucesso foi exibida
            $successMsg = Condition::presenceOfElementLocated(By::cssSelector('.explanation'));
            $this->driver->wait()->until($successMsg);

            if($successMsg) {
                return true;
            } else {
                return false;
            }

            // Não encerra a sessão para que seja possível ver o resultado
            //$this->driver->quit();
        } catch (\Exception $e) {
            return $e;
        }
    }


    /**
     * Faz o upload de um arquivo para o elemento de input com nome especificado.
     *
     * @param $driver instância do driver do navegador
     * @param string $elementName nome do elemento de input do arquivo
     * @param string $filePath caminho do arquivo que será enviado
     * @return void
     */
    private function __uploadFile($driver, $elementName, $filePath)
    {
        $fileInput = $driver->findElement(By::name($elementName));

        $fileInput->setFileDetector(new FileDetector());
        
        $fileInput->sendKeys($filePath);
    }


    /**
     * Realiza o upload do arquivo na página de teste
     *
     * Esse método navega até a página de upload de teste e realiza o upload do arquivo Teste TKS
     *
     * @return Array - Array com as indicações se a operação foi bem sucedida ou não
     */
    public function uploadFile($filePath, $fileName)
    {
        try {

            // Cria a variavel de retorno
            $return = [
                "success" => false,
                "message" => ""
            ];

            // Verifica se o arquivo existe
            $filePath = $filePath . $fileName;
            if (file_exists($filePath)) {
                
                $this->driver = $this->browser();

                $this->driver->get($this->clientSite['upload']);

                // Realiza o upload do arquivo
                $this->__uploadFile($this->driver, "filename", $filePath);

                // Seleciona o filetype com o valor text
                $filetypeField = $this->driver->findElement(By::cssSelector('input[value="text"]'));
                $filetypeField->click();

                // Submete o formulário
                $submitBtn = $this->driver->findElement(By::cssSelector('input[type="submit"]'));
                $submitBtn->click();

                // Verifica se a página de sucesso foi exibida
                $successMsg = Condition::presenceOfElementLocated(By::cssSelector('.explanation'));
                $this->driver->wait()->until($successMsg);

                // Não encerra a sessão para que seja possível ver o resultado
                //$this->driver->quit();

                if($successMsg) {
                    $return = [
                        "success" => true,
                        "message" => "O upload foi realizado com sucesso."
                    ];
                } else {
                    $return = [
                        "success" => false,
                        "message" => "Não foi possível realizar o upload."
                    ];
                }

            } else {
                $return = [
                    "success" => false,
                    "message" => "O arquivo não foi encontrado."
                ];
            }

            return $return;

        } catch (\Exception $e) {
            return $e;
        }
    }


    /**
     * Método responsável por baixar o arquivo
     *
     * @param string $downloadPath Diretório onde o arquivo será salvo.
     * @param string $fileName Nome do arquivo que será salvo.
     */
    public function downloadFile($downloadPath, $fileName)
    {
        try {
            $this->driver = $this->browser();

            $this->driver->get($this->clientSite['download']);

            // Encontra o botão "direct-download-a"
            $downloadButton = $this->driver->findElement(By::id('direct-download-a'));

            // Altera o nome do arquivo
            $this->driver->executeScript("arguments[0].setAttribute('download', '".$fileName."');", array($downloadButton));

            // Clica no botão para realizar o download
            $downloadButton->click();

            // Caminho completo do arquivo
            $filePath = $downloadPath . $fileName;

            // Espera até que o arquivo seja baixado
            $timeout = 20;
            while (!file_exists($filePath ) && $timeout > 0) {
                sleep(3);
                $timeout--;
            }

            
            // Verifica se o arquivo foi baixado
            if (file_exists($filePath)) {
                return true;
            } else {
                return false;
            }

            $this->driver->quit();
        } catch (\Exception $e) {
            return $e;
        }
    }

}