<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{

    protected $table = 'client';
    protected $primaryKey = 'id_client';

    use HasFactory;


    /**
     * Insere um novo registro na tabela.
     *
     * @param array $dados Os dados a serem inseridos na tabela.
     * @return Client - modelo do registro criado.
     */
    public function create(array $dados)
    {
        return self::insert($dados);
    }

    /**
     * Recupera um registro da tabela pelo ID.
     *
     * @param int $id O ID do registro a ser recuperado.
     * @return Client - O modelo do registro ou null se não encontrado.
     */
    public function findById(int $id)
    {
        return self::find($id);
    }

    /**
     * Recupera todos os registros da tabela.
     *
     * @return Collection - Toda a collection.
     */
    public function getAll()
    {
    return self::all();
    }

    /**
     * Atualiza um registro na tabela pelo ID.
     *
     * @param int $id O ID do registro a ser atualizado.
     * @param array $dados Os novos dados do registro.
     * @return bool - True se a atualização foi bem sucedida, false caso contrário.
     */
    public function update(array $dados = [], array $options = [])
    {
        return self::whereId($dados['id'])->update($dados, $options);
    }

    /**
     * Exclui um registro da tabela pelo ID.
     *
     * @param int $id O ID do registro a ser excluído.
     * @return bool - True se a exclusão foi bem sucedida, false caso contrário.
     */
    public function delete()
    {
        return self::delete();
    }

    
}
