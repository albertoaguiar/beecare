<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class PdfController extends Controller
{
    public function convertPdfToCsv()
    {
        try {

            // Seta as variaveis necessarias para converter o PDF em texto e recupera as páginas do PDF
            $pdfFilePath = public_path("pdfs/pdf_test.pdf");
            $parser = new Parser();
            $pdf = $parser->parseFile($pdfFilePath);
            $pages = $pdf->getPages();

            // Seta as variaveis que receberão as informações de protocolo e da guia
            $protocolData = null;
            $guiasData = [];

            // Percorre todas as páginas para recuperar as informações necessárias
            foreach ($pages as $key => $page) {
                $text = $page->getText();

                // Se for a página 0, pego as informações de protocolo
                if ($key == 0) {
                    $protocolData = $this->__getProtocolData($text);
                } else {

                    // Verifico se a página é uma guia
                    if (strpos($text, 'DADOS DA GUIA') !== false) {

                        $guiaData = $this->__getGuiaData($text);

                        $guiaPage['protocol_page'] = $protocolData;
                        $guiaPage['top_result'] = $guiaData['top_result'];
                        $guiaPage['procedures'] = $guiaData['procedures'];

                        if (!empty($guiasData)) {
                            $lastKey = count($guiasData) - 1;

                            $lastValue = $guiasData[$lastKey];

                            // Verifica se é o mesmo beneficiario
                            if ($lastValue['top_result']['Nome do Beneficiário'] == $guiaPage['top_result']['Nome do Beneficiário']) {
                                $guiasData[$lastKey]['top_result'] = $guiaPage['top_result'];
                                foreach ($guiaPage['procedures'] as $procedure) {
                                    $guiasData[$lastKey]['procedures'][] = $procedure;
                                }
                            } else {
                                $guiasData[] = $guiaPage;
                            }

                        } else {
                            $guiasData[] = $guiaPage;
                        }
                    }
                }
            }

            // Cabeçalho do arquivo
            if(isset($guiasData[0])) {
                $protocolPage = array_keys($guiasData[0]['protocol_page']);
                $head = array_keys($guiasData[0]['top_result']);
                $head = array_merge($protocolPage, $head);

                foreach ($guiasData[0]['procedures'] as $procedure) {
                    $head = array_merge($head, array_keys($procedure));
                }
            }

            // Inicia e abre o arquivo para inserir dados
            $file = fopen(public_path('pdf_to_csv.csv'), 'w');
            
            // Insere o cabeçalho no arquivo
            fputcsv($file, $head);

            // Monta os dados do cabeçalho da página com os dados da listagem de procedimentos
            foreach ($guiasData as $item) {
                
                foreach ($item['procedures'] as $procedure) {
                    $procedureLine = [
                        trim($procedure['Data Realização'] ?? ''),
                        trim($procedure['Tabela/Item assistencial'] ?? ''),
                        trim($procedure['Código do Procedimento'] ?? ''),
                        trim($procedure['Descrição'] ?? ''),
                        trim($procedure['Grau de Participação'] ?? ''),
                        trim($procedure['Valor informado'] ?? ''),
                        trim($procedure['Quant. Executada'] ?? ''),
                        trim($procedure['Valor processado'] ?? ''),
                        trim($procedure['Valor liberado'] ?? ''),
                        trim($procedure['Valor Glossa'] ?? ''),
                        trim($procedure['Código da Glosa do Procedimento'] ?? '')
                    ];

                    // Junta todas as informações para adicionar no arquivo
                    $line = array_merge(array_values($item['protocol_page']), array_values($item['top_result']));
                    $line = array_merge($line, array_values($procedureLine));

                    fputcsv($file, $line);
                }
            }

            // Fecha o arquivo
            fclose($file);

            // Efetua o download do arquivo
            return response()->download(public_path('pdf_to_csv.csv'), 'pdf_to_csv.csv', [
                'Content-Type' => 'text/csv',
            ]);

        } catch (\Exception $e) {
            return $e;
        }
    }

    /**
     * Extrai informações sobre o protocolo
     *
     * @param string - $text O texto contendo os dados de protocolo de pagamento de saúde.
     *
     * @return array Um array associativo com as informações extraídas.
     */
    private function __getProtocolData($text)
    {
        $pattern = [
            "registro_ans" => "/1 - Registro ANS\n(\d+)/",
            "nome_operadora" => "/3 - Nome da Operadora\n(.*?)4 -/",
            "cod_na_operadora" => "/6 - Código na Operadora(\d+)/",
            "nome_contratado_1" => "/6 - Código na Operadora(\d+)/",
            "nome_contratado_2" => "/6 - Código na Operadora\s*\K\d+\s*(.*?)\s*7 - Nome do Contratado/",
            "num_lote" => "/(?<=Código CNES)\s*(\d+)/",
            "num_protocolo" => "/9 - Número do Lote(\d+)/",
            "data_protocolo" => "/10 - Nº do Protocolo \(Processo\)(\d+\/\d+\/\d{4})/",
            "cod_glosa_protocolo" => '/12 - Código da Glosa do Protocolo(\d*)/',
            "valor_informado_protocolo" => '/(?<=\d\s)\d{1,3}(?:\.\d{3})*(?:,\d{2})/',
            "valor_informado_geral" => '/TOTAL DO PROTOCOLO\s+([\d.,]+)/',
            "valores_39_40_41" => '/(?<=38 - Valor Informado do Protocolo \(R\$\))\s*([\d.,]+\s+){2}[\d.,]+/',
            "valores_43_44_45" => '/(?<=Valor Informado Geral \(R\$\))\s*([\d.,]+\s+){2}[\d.,]+(?=\s+43 - Valor Processado Geral \(R\$\))/',
        ];
        
        // Realiza o preg dos padrões
        $patternPregResult = $this->__pregMatch($pattern, $text);
        
        // Concatena as informações para montar o nome do contratado
        $nomeContratado = "";
        if(isset($patternPregResult[3][1]) && isset($patternPregResult[4][1])) {
            $nomeContratado = $patternPregResult[3][1] ." ". $patternPregResult[4][1];
        }
        
        // Pega os valores dos itens 39, 40 e 41 (valores do protocolo)
        $protocolValues = explode(' ', $patternPregResult[11][0]);
        
        // Pega os valores dos itens 43, 44 e 45 (valores gerais)
        $generalValues = explode(' ', $patternPregResult[12][0]);

        $return = [
            'Registro ANS' => isset($patternPregResult[0][1]) ? $patternPregResult[0][1] : "",
            'Nome da Operadora' => isset($patternPregResult[1][1]) ? trim($patternPregResult[1][1]) : "",
            'Código na Operadora' => isset($patternPregResult[2][1]) ? $patternPregResult[2][1] : "",
            'Nome do Contratado' => isset($nomeContratado) ? $nomeContratado : "",
            'Número do Lote' => isset($patternPregResult[5][1]) ? $patternPregResult[5][1] : "",
            'Nº do Protocolo (Processo)' => isset($patternPregResult[6][1]) ? $patternPregResult[6][1] : "",
            'Data do Protocolo' => isset($patternPregResult[7][1]) ? $patternPregResult[7][1] : "",
            'Código da Glosa do Protocolo' => isset($patternPregResult[8][1]) ? $patternPregResult[8][1] : "",
            'Valor Informado do Protocolo (R$)' => isset($patternPregResult[9][0]) ? $patternPregResult[9][0] : "",
            'Valor Processado do Protocolo (R$)' => isset($protocolValues[1]) ? $protocolValues[1] : "",
            'Valor Liberado do Protocolo (R$) (R$)' => isset($protocolValues[2]) ? $protocolValues[2] : "",
            'Valor Glosa do Protocolo (R$)' => isset($protocolValues[3]) ? $protocolValues[3] : "",
            'Valor Informado Geral (R$)' => isset($patternPregResult[10][1]) ? $patternPregResult[10][1] : "",
            'Valor Processado Geral (R$)' => isset($generalValues[1]) ? $generalValues[1] : "",
            'Valor Liberado Geral (R$)' => isset($generalValues[2]) ? $generalValues[2] : "",
            'Valor Glosa Geral (R$)' => isset($generalValues[3]) ? $generalValues[3] : ""
        ];

        return $return;
    }

    /**
     * Retorna um array com informações da guia
     * 
     * @param string $text - O texto formatado contendo as informações da guia
     * @return array - Array com informações da guia e dos procedimentos realizados
     */
    private function __getGuiaData($text)
    {
        $pattern = [
            "num_guia_prestador" => '/DADOS DA GUIA(\d+)\s+13 - Número da Guia no Prestador/',
            "num_guia_atrib_oper_1" => '/(?<=Número da Guia no Prestador)(\d+)/',
            "num_guia_atrib_oper_2" => '/13 - Número da Guia no Prestador\d+\s+(- \d+)/',
            "senha" => '/14 - Número da Guia Atribuido pela Operadora(\d+)\s+15 - Senha/',
            "nome_beneficiario" => '/15 - Senha([\s\S]+?)16 - Nome do Beneficiário/',
            "numero_carteira" => '/16 - Nome do Beneficiário([\s\S]+?)17 - Número da Carteira/',
            "data_inicio_faturamento" => '/17 - Número da Carteira([\s\S]+?)18 - Data Início do Faturamento/',
            "hora_inicio_faturamento" => '/20 - Data Fim do Faturamento([\s\S]+?)19 - Hora Início do Faturamento/',
            "data_fim_faturamento" => '/18 - Data Início do Faturamento([\s\S]+?) 20 - Data Fim do Faturamento/',
            "cod_glosa" => '/TOTAL DA GUIA\s*\r?\n\s*([\d.,]+.*)/s'
        ];

        // Realiza o preg dos padrões
        $patternPregResult = $this->__pregMatch($pattern, $text);

        // Recupera o número da guia pela operadora
        $numGuiaByOperadora = "";
        if(isset($patternPregResult[1][1]) && isset($patternPregResult[2][1])) {
            $numGuiaByOperadora = $patternPregResult[1][1] . $patternPregResult[2][1];
        }
        

        // Recupera o código da glosa
        $glosaCode = isset($patternPregResult[9][1]) ? $patternPregResult[9][1] : "";
        $codeLines = explode("\n", $glosaCode);
        $firstLine = isset($codeLines[0]) ? $codeLines[0] : null;
        preg_match('/(?<=,)\d{2}(.*)/', $firstLine, $glosaCod);

        // Recupera os valores da guia
        $strSub = 'Código da Glosa34 - Valor Informado da Guia (R$)';
        $pos = strpos($text, $strSub);
        $glosaValues = '';
        if ($pos !== false) {
            $glosaValues = substr($text, $pos + strlen($strSub));
        }
        $generalGlosaValues = explode(" ", $glosaValues);
        
        $topResult = [
            'Número da Guia no Prestador' => isset($patternPregResult[0][1]) ? $patternPregResult[0][1] : "",
            'Número da Guia Atribuido pela Operadora' => isset($numGuiaByOperadora) ? $numGuiaByOperadora : "",
            'Senha' => isset($patternPregResult[3][1]) ? $patternPregResult[3][1] : "",
            'Nome do Beneficiário' => isset($patternPregResult[4][1]) ? $patternPregResult[4][1] : "",
            'Número da Carteira' => isset($patternPregResult[5][1]) ? $patternPregResult[5][1] : "",
            'Data Início do Faturamento' => isset($patternPregResult[6][1]) ? $patternPregResult[6][1] : "",
            'Hora Início do Faturamento' => isset($patternPregResult[7][1]) ? $patternPregResult[7][1] : "",
            'Data Fim do Faturamento' => isset($patternPregResult[8][1]) ? $patternPregResult[8][1] : "",
            'Código da Glosa da Guia' => isset($glosaCod[1]) ? $glosaCod[1] : "",
            'Valor Informado da Guia' => isset($generalGlosaValues[1]) ? $generalGlosaValues[1] : "",
            'Valor Processado da Guia' => isset($generalGlosaValues[1]) ? $generalGlosaValues[1] : "",
            'Valor Liberado da Guia' => isset($generalGlosaValues[2]) ? $generalGlosaValues[2] : "",
            'Valor Glosa da Guia' => isset($generalGlosaValues[3]) ? $generalGlosaValues[3] : ""
        ];

        $procedures = $this->__checkProceduresLines($text);

        $result = ['top_result' => $topResult, 'procedures' => $procedures];

        return $result;

    }

    /**
     * Verifica os procedimentos de uma página
     * 
     * @param string $text - O texto formatado contendo as informações da guia
     * @return array - Array dos procedimentos realizados
     */
    private function __checkProceduresLines($text)
    {
        // Cria variável de retorno
        $return = [];

        // Buscar a frase necessária para a verificação de linhas de procedimentos
        $keyword = strpos($text, '22 - Código da Glosa da Guia');

        // Recuperar a data após a indicação do código da glosa
        if (preg_match('/\d{2}\/\d{2}\/\d{4}/', $text, $date, 0, $keyword)) {
            $date = isset($date[0]) ? $date[0] : "";
        } else {
            $date = null;
        }

        $textAfterKeyword = substr($text, $keyword);
        $lines = explode("\n", $textAfterKeyword);


        // Verificar se há mais de um procedimento na guia (o que ocorre se a página tiver mais de 10 linhas)
        if (count($lines) > 10) {
            $return = $this->__getProcedures($text, $lines);
        } else {
            array_push($return, $this->__getProcedure($text, $lines));
        }

        return $return;
    }

    
    /**
     * Recupera um procedimentos quando há mais de um procedimento na página
     * 
     * @param string - $text O texto que contém as informações do procedimento.
     * @param array - $lines As linhas do texto que contêm as informações do procedimento.
     * @return array - Array dos procedimentos realizados
     */
    private function __getProcedures($text, $lines)
    {

        // Cria o array que irá receber os procedimentos realizados
        $procedures = [];

        // Seta como null a primeira ocorrencia do item Tabela/Item Assistencial
        $tabelaAssitencialFirst = null;

        // Varre as linhas da pagina
        foreach ($lines as $key => $line) {

            // Seta como null a data de realização e a tabela/item assistencial
            $realizationDate = null;
            $tabelaAssistencial = null;

            // Verifica se na lista existe uma data
            if (preg_match('/\d{2}\/\d{2}\/\d{4}/', $line, $dateFound)) {

                // Se existe uma data, seta o seu valor na variavel que popula a Data de realização
                $realizationDate = isset($dateFound[0]) ? $dateFound[0] : "";

                // Verifica se a tabela/item assistencial ainda é null
                if ($tabelaAssistencial === null) {

                    // Se for cria um novo array que recebe a data de realização do procedimento
                    $procedures[] = ['Data Realização' => $realizationDate];
                }
            } else {

                // Se não houver data na linha, a primeira tabela/item assistencial a ser setado é o que consta na chave anterior
                $tabelaAssitencialFirst = $key - 1;
                break;
            }
        }

        // Define como null a última tabela/item assistencial e verifica a quantidade de procedimentos existente no array
        $tabelaAssistencialLast = null;
        $proceduresCount = count($procedures);

        // Verifica se é a primeira tabela, se não for cai no if
        if ($tabelaAssitencialFirst !== null) {

            // Enquanto houver itens no array, popula o Tabela/Item Assistencial
            for ($i = 0; $i < $proceduresCount; $i++) {
                
                // Salva a última key que foi encontrado um valor de Tabela/Item Assistencial
                $tabelaAssistencialLast = $tabelaAssitencialFirst + $i;

                // Pega a linha para recuperar a informação necessária
                $line = $lines[$tabelaAssitencialFirst + $i];

                // Deixa somente o conteúdo de Tabela/Item assistencial
                $line = preg_replace('/\d{2}\/\d{2}\/\d{4}/', '', $line);
                $tabelaAssistencial = substr($line, 0, 2);
                $procedures[$i]['Tabela/Item assistencial'] = $tabelaAssistencial;
            }
        }

        // Seta como null a quantidade de códigos de procedimento e a última linha que foi encontrada o valor do mesmo
        $procedureCodCount = null;
        $procedureCodLastRow = null;

        // Verifica se é a última informação de Tabela/Item Assistencial
        if ($tabelaAssistencialLast !== null) {

            // Enquanto houver itens no array popula o Código do Procedimento
            for ($i = 0; $i < $proceduresCount; $i++) {

                // Pega a linha para recuperar a informação necessária
                $line = $lines[$tabelaAssistencialLast + $i];

                // Seta que a ultima linha que tem o código é a proxima para seguir no for
                $procedureCodLastRow = $tabelaAssistencialLast + $i;

                // Verifica se é o primeiro item do array
                if ($i === 0) {

                    // Recupera a informação necessária para setar no array
                    $pattern = '/^.{2}(.*)$/';
                    $procedureCod = preg_replace($pattern, '$1', $line);
                    $procedures[$i]['Código do Procedimento'] = $procedureCod;

                    // Recupera a quantidade de códigos
                    $procedureCodCount = strlen($procedureCod);

                } else if (($proceduresCount - $i) == 1) {

                    // Se for o último item o regex conta com o numero do contador para efetuar a busca
                    $pattern = '/^(\d{' . $procedureCodCount  . '})/';
                    preg_match($pattern, $line, $procedureCod);
                    $procedureCod = $procedureCod[1];
                    $procedures[$i]['Código do Procedimento'] = $procedureCod;
                } else {

                    // Simplesmente coloca o valor da linha no item do array
                    $procedures[$i]['Código do Procedimento'] = $line;
                }
            }
        }

        // Seta como null a primeira ocorrencia do item valor informado e como '' a descrição
        $description = '';
        $firstRowValorInformado = null;

        // Verifica se é a última linha do código de procedimento
        if ($procedureCodLastRow !== null) {

            // Enquanto não for a última linha
            for ($i = $procedureCodLastRow; $i < count($lines); $i++) {

                // Seta o valor da linha atual
                $line = $lines[$i];

                // Se for a primeira linha faz o regex 
                if ($i == $procedureCodLastRow) {

                    if (preg_match('/\d/', $line)) {
                        $description = substr($line, $procedureCodCount);
                    }
                } else {

                    // Verifica se tem números ou não
                    if (preg_match('/\d+,\d+/', $line) ) {
                        $firstRowValorInformado = $i;
                        break;
                    } else {
                        if (!empty($line))
                            $description .= $line;
                    }
                }
            }
        }

        // Adiciona a descrição no array de procedimentos
        foreach ($procedures as $key => $value) {
            $procedures[$key]['Descrição'] = $description;
        }

        // Não há nenhuma informação em nenhum dos graus de participação, dessa maneira deixo tudo como uma string vazia
        foreach ($procedures as $key => $value) {
            $procedures[$key]['Grau de Participação'] = '';
        }

        // Seta como null a ultima linha de ocorrencia do valor informado
        $lastRowValorInformado = null;

        // Verifica se não é a primeira linha
        if ($firstRowValorInformado !== null) {

            // Enquanto houver itens no array, popula a informação de Valor Informado
            for ($i = 0; $i < $proceduresCount; $i++) {
                $line = $lines[$firstRowValorInformado + $i];

                // Verifica se existe somente float
                if (preg_match('/^\s*\d+,\d*\s*$/', $line)) {

                    // Adiciona o valor no array e seta a ultima linha como a próxima para seguir populando
                    $procedures[$i]['Valor informado'] = $line;
                    $lastRowValorInformado = $firstRowValorInformado + $i;
                    continue;
                } else {

                    // Seta o valor com o encontrado no regex e para a execução
                    preg_match('/\d+,\d+/', $line, $valorInformado);
                    $procedures[$i]['Valor informado'] = isset($valorInformado[0]) ? $valorInformado[0] : "";

                    $lastRowValorInformado = $firstRowValorInformado + $i;
                    break;
                }
            }
        }

        // Seta como null a ultima linha de Quant. Executada
        $lastRowQtdExecutada = null;

        // Verifica se não é a ultima linha de Valor Informado
        if ($lastRowValorInformado !== null) {

            // Enquanto houver itens no array, popula a informação de Quant. Executada
            for ($i = 0; $i < $proceduresCount; $i++) {
                $line = $lines[$lastRowValorInformado + $i];

                // Verifica se é o primeiro item do array
                if ($i == 0) {
                    preg_match('/\d+(?=[^\d,.]*$)/', $line, $qtdExecutada);
                    $procedures[$i]['Quant. Executada'] = isset($qtdExecutada[0]) ? $qtdExecutada[0] : "";
                } else {
                    
                    // Verifica se não existe float
                    if (preg_match('/^\s*\d+\s*$/', $line)) {
                        $procedures[$i]['Quant. Executada'] = $line;
                    } else {

                        // Popula a informação da ultima linha entrada
                        preg_match('/\d+\s/', $line, $qtdExecutada);
                        $procedures[$i]['Quant. Executada'] = isset($qtdExecutada[0]) ? $qtdExecutada[0] : "";

                        $lastRowQtdExecutada = $lastRowValorInformado + $i;
                    }
                }
            }
        }

        // Seta como null a última linha do valor processado
        $lastRowValorProcessado = null;

        // Verifica se não é a última linha de Quant. Executada 
        if ($lastRowQtdExecutada !== null) {

            // Enquanto houver itens no array, popula a informação de Quant. Executada
            for ($i = 0; $i < $proceduresCount; $i++) {
                $line = $lines[$lastRowQtdExecutada + $i];
                
                // Verifica se é o primeiro item
                if ($i == 0) {
                    if (preg_match('/^\s*\d+\s+(\d+,\d+)/', $line, $valorProcessado)) {
                        $valorProcessado = str_replace(',', '.', $valorProcessado[1]);
                        $procedures[$i]['Valor processado'] = $valorProcessado;
                    }
                } else {
                    
                    // Verifica se é o último item do array
                    if ($i == ($proceduresCount - 1)) {
                        preg_match('/\d+,\d+/', $line, $valorProcessado);
                        $procedures[$i]['Valor processado'] = isset($valorProcessado[0]) ? $valorProcessado[0] : "";
                        $lastRowValorProcessado = $lastRowQtdExecutada + $i;
                    } else {
                        $procedures[$i]['Valor processado'] = $line;
                    }
                }
            }
        }

        // Seta como null a ultima linha de valor liberado
        $lastRowValorLiberado = null;

        // Verifica se não é o último valor processado
        if ($lastRowValorProcessado !== null) {

            // Enquanto houver itens no array, popula a informação de Quant. Executada
            for ($i = 0; $i < $proceduresCount; $i++) {
                $line = $lines[$lastRowValorProcessado + $i];

                // Verifica se é o primeiro item
                if ($i == 0) {
                    if (preg_match('/\d+,\d+\s+(\d+,\d+)/', $line, $valorLiberado)) {
                        $procedures[$i]['Valor liberado'] = $valorLiberado[1];
                    }
                } else {
                    
                    // Verifica se é o último item do array
                    if ($i == ($proceduresCount - 1)) {
                        preg_match('/\d+,\d+/', $line, $valorLiberado);
                        $procedures[$i]['Valor liberado'] = isset($valorLiberado[0]) ? $valorLiberado[0] : "";
                        $lastRowValorLiberado = $lastRowValorProcessado + $i;
                    } else {
                        $procedures[$i]['Valor liberado'] = $line;
                    }
                }
            }
        }

        // Verifica se não é o ultimo valor liberado
        if ($lastRowValorLiberado !== null) {

            // Enquanto houver itens no array, popula a informação de Quant. Executada
            for ($i = 0; $i < $proceduresCount; $i++) {
                $line = $lines[$lastRowValorLiberado + $i];

                // Verifica se é o primeiro item do array
                if ($i == 0) {
                    preg_match_all('/\d+,\d+/', $line, $valorGlosa);
                    $procedures[$i]['Valor Glossa'] = $valorGlosa[0][1];
                } else {
                    
                    // Verifica se é o último item
                    if ($i == ($proceduresCount - 1)) {
                        preg_match('/^\s*\d+,\d+/', $line, $valorGlosa);
                        $procedures[$i]['Valor Glossa'] = $valorGlosa[0];
                    } else {
                        $procedures[$i]['Valor Glossa'] = $line;
                    }
                }
            }
        }

        // Cria o array de código glosa e seta um contador para verificar quando acabar a quantidade de itens, visto que esse é o ultimo item da linha
        $glosaCount = 0;
        $glosaCode = [];

        // Faz o foreach com as linhas reverse para pegar do último para o primeiro item (que no caso é o código da glosa)
        foreach (array_reverse($lines) as $line) {

            // Adiciona o item no array de glosa
            $glosaCode[] = $line;
            $glosaCount++;

            // Se a qntd de glosa for igual ou maior que a quantidade de procedimentos, para a execução, pois acabou
            if ($glosaCount >= $proceduresCount) {
                break;
            }
        }

        $glosaCode = array_reverse($glosaCode);

        // Enquanto houver itens no array principal, adiciona o código da glosa
        for ($i = 0; $i < count($procedures); $i++) {
            $procedures[$i]['Código da Glosa do Procedimento'] = $glosaCode[$i];
        }

        return $procedures;
    }


    /**
     * Recupera informações de um único procedimento a partir das linhas de um texto.
     *
     * @param string - $text O texto que contém as informações do procedimento.
     * @param array - $lines As linhas do texto que contêm as informações do procedimento.
     * @return array As informações do procedimento.
     */
    private function __getProcedure($text, $lines)
    {

        // Junta as linhas para formar uma só informações
        $datas = $lines[0] . " " . $lines[1];

        // Recupera a data de realização
        $pattern = '/Código da Glosa da Guia(\d{2}\/\d{2}\/\d{4})/';
        if (preg_match($pattern, $datas, $realizationDate)) {
            $realizationDate = $realizationDate[1];
            $datas = substr($datas, strpos($datas, $realizationDate) + strlen($realizationDate));
        }

        // Recupera a Tabela
        $tabelaAssistencial = substr($datas, 0, 2);
        $datas = substr($datas, 2);

        // Recupera codigo do procedimento
        $procedureCod = substr($datas, 0, 8);
        $datas = substr($datas, 8);

        // Recupera a descrição
        $pattern = '/^(.*?)(\d+,\d+)/';
        preg_match($pattern, $datas, $description);

        // Recupera informações para compor o valor
        preg_match('/\d+,\d+.*$/', $datas, $needle);
        $datas = isset($needle[0]) ? $needle[0] : "";

        // Recupera os valores dos últimos itens do array
        $values = explode(' ', $datas);

        // Monta o procedimento a ser retornado
        $procedure = [
            "Data Realização" => $realizationDate,
            "Tabela/Item assistencial" => $tabelaAssistencial,
            "Código do Procedimento" => $procedureCod,
            "Descrição" => $description[1],
            "Grau de Participação" => "", // Não há nenhum dado
            "Valor informado" => isset($values[0]) ? $values[0] : "",
            "Quant. Executada" => $values[1],
            "Valor processado" => $values[2],
            "Valor liberado" => $values[3],
            "Valor Glossa" => $values[4],
            "Código da Glosa do Procedimento" => last($lines)
        ];

        return $procedure;
    }

    /**
     * 
     * Realiza uma busca de padrões em um texto usando a função preg_match() do PHP.
     * 
     * @param array - $pattern Um array contendo os padrões de busca em formato de expressão regular.
     * @param string - $text O texto onde a busca será realizada.
     * @return array - Um array contendo os resultados da busca para cada padrão fornecido.
     */
    private function __pregMatch($pattern, $text)
    {
        $return = [];

        foreach ($pattern as $key => $value) {
        // Realiza a busca do padrão no texto usando a função preg_match().
        preg_match($value, $text, $result);
        // Adiciona o resultado ao array de retorno.
        array_push($return, $result);
        }

        // Retorna o array de resultados.
        return $return;
    }
}