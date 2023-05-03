<?php

namespace App\Http\Controllers;

use App\Http\Controllers\testpages\Testpages;
use App\Models\Client;

class ClientController
{

    /**
     * Insere os registros recuperados na tabela do testpage.
     *
     * @return bool - true se foi inserido com sucesso, false caso contrário.
     */
    public function insertClient()
    {

        // Cria uma nova instância da class Testpages
        $testpages = new Testpages();

        // Recupera as informações da tabela do testpage
        $data = $testpages->getDataTable();

        // Formata o array para inserção no banco de dados
        $dataFormated = $this->__formatToDatabase($data);

        // Cria uma nova instância do modelo Client
        $client = new Client();

        // Chama o método insertClient() na instância do modelo
        if ($client->create($dataFormated)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Nomeia as colunas para a inserção das informações no banco de dados
     *
     * @return Array - Retorna o array formatado para a inserção no banco.
     */
    private function __formatToDatabase($data)
    {
        // Variavel de retorno
        $return = [];

        // Varre o parametro para formata-lo para inserção no banco
        foreach ($data as $key => $value) {
            $arr = ["ds_name" => $value[0], "nr_amount" => $value[1]];
            array_push($return, $arr);
        }

        return $return;
    }

    /**
     * Preenche os dados do formulário de teste
     *
     * @return Array - Array com as indicações se a operação foi bem sucedida ou não
     */
    public function fillForm()
    {
        // Cria a variavel de retorno
        $return = [
            "success" => false,
            "message" => ""
        ];

        // Cria uma nova instância da class Testpages
        $testpages = new Testpages();

        // Executa o preenchimento do formulário
        $sendForm = $testpages->fillForm();

        // Verifica se o form foi enviado com sucesso
        if($sendForm) {
            $return = [
                "success" => true,
                "message" => "O formulário foi enviado com sucesso!"
            ];
        } else {
            $return = [
                "success" => false,
                "message" => "Ocorreu algum erro ao tentar submeter o formulário."
            ];
        }

        return $return;
    }


    /**
     * Realiza o download da página de teste
     *
     * @return Array - Array com as indicações se a operação foi bem sucedida ou não
     */
    function downloadFile()
    {
        // Cria a variavel de retorno
        $return = [
            "success" => false,
            "message" => ""
        ];

        // Cria uma nova instância da class Testpages
        $testpages = new Testpages();

        // Realiza o download passando como parametro o diretório e o nome do arquivo
        $download = $testpages->downloadFile("C:/users/alber/Downloads/", "Teste TKS.txt");

        // Verifica se o download foi realizado com sucesso
        if($download) {
            $return = [
                "success" => true,
                "message" => "O download foi realizado com sucesso!"
            ];
        } else {
            $return = [
                "success" => false,
                "message" => "Ocorreu algum erro ao tentar realizar o download."
            ];
        }

        return $return;
    }


    /**
     * Realiza o upload do arquivo baixado para a página de teste
     *
     * @return Array - Array com as indicações se a operação foi bem sucedida ou não
     */
    function uploadFile()
    {

        // Cria uma nova instância da class Testpages
        $testpages = new Testpages();

        // Realiza o upload passando como parametro o diretório e o nome do arquivo
        $upload = $testpages->uploadFile("C:/users/alber/Downloads/", "Teste TKS.txt");

        return $upload;
    }
}