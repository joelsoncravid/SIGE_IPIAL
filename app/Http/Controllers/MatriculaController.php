<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\MatriculaRequest;
// use App\Http\Requests\MatriculaStoreRequest;
use Illuminate\Http\Request;
use App\Models\{
    Candidato,
    User,
};
use App\Traits\PessoaTrait;
use Illuminate\Support\Facades\Redirect;

class MatriculaController extends Controller
{
    use PessoaTrait;

    public function index(){
        return view('matricula/matriculas');
    }
    public function create($id)
    {
        $candidato = CandidatoController::pegarDadosCandidato($id);
        return view('matricula.matricular-aluno',[
            'candidato' => $candidato[0]
        ]);
    }

    public function store(MatriculaRequest $input)
    {
        $request = $input->validated(); // Inputs validadas
        // dd($request);

        // Atualizando candidato(Pessoas) pela confirmação de dados no formMatricula--
        $candidato = Candidato::find($request['id']);
        $pessoa_id = $candidato->pessoa_id;
        $dadosPessoa = [
            'nome_completo'=> $request['nome_completo'],
            'num_bi'=> $request['num_bi'],
            'genero'=> $request['genero'],
            'telefone' => $request['num_tel'],
            'pessoa_id' => $pessoa_id
        ];
        $Pessoa = $this->updatePessoa($dadosPessoa);

        if(!$Pessoa)
        {
            $msg="Fique atento nos dados de identifcação, este candidato já está inscrito!";
            return redirect()->back()->with("ErroPessoa",$msg);
        }

        // Atualizando candidato(Escola) pela confirmação de dados no formMatricula--
        $escola_id = $candidato->escola_proveniencia_id;
        $dadosEscola = [
            'nome_escola'=>$request['nome_escola'],
            'turno'=>$request['turno'],
            'num_aluno'=>$request['num_aluno'],
            'turma_aluno' =>$request['turma_aluno'],
            'ultimo_anoLectivo' =>$request['ultimo_anoLectivo'],
            'escola_proveniencia_id' => $escola_id
        ];
        $Escola = EscolaController::updateEscola($dadosEscola);

        if(!$Escola)
        {
            $msg="Lamentamos! Certifique de que introduziu os dados correctos ou tente mais tarde...";
            return redirect()->back()->with("ErroCadastro",$msg);
        }

        $dadosCandidato=[
            'nome_pai_cand'=>$request['nome_pai_cand'],
            'nome_mae_cand'=>$request['nome_mae_cand'],
            'candidato_id' => $request['id']
        ];
        $candidato = CandidatoController::updateCandidato($dadosCandidato);
        if(!$candidato)
        {
            $msg="Lamentamos! Dados não cadastrado, tente este processo mais tarde...";
            return redirect()->back()->with("ErroCadastro",$msg);
        }

        $curso_id = CursoController::pegarIdCurso($request['curso_escolhido']);
        // dd($curso_id);
        $dadosAluno=[
            'curso_id'=>$curso_id,
            'status'=> 0,
            'candidato_id' =>intval($request['id'])
        ];
        $alunoId = AlunoController::store($dadosAluno);

        if(!$alunoId)
        {
            $msg="Lamentamos! Dados não cadastrado, tente este processo mais tarde...";
            return redirect()->back()->with("ErroCadastro",$msg);
        }

        for($i = 1; $i <= 3; $i++)
        {
            $dadosPessoa = [
                'nome_completo'=> $request['nome_enc' . $i],
                'num_bi'=> strtoupper($request['num_bi_enc' . $i]),
                'data_nascimento'=> $request['data_nascimento_enc' . $i],
                'genero'=> $request['genero'. $i],
                'telefone' => $request['telefone' . $i]
            ];

            $idPessoa = $this->storePessoa($dadosPessoa);
            if(!$idPessoa)
            {
                $msg="Fique atento nos dados de identifcação, este candidato já está inscrito!";
                return redirect()->back()->with("ErroPessoa",$msg);
            }
            $dadosEncarregado = [
                'grau_parentensco_enc'=> $request['grau' . $i],
                'pessoa_id'=> $idPessoa
            ];

            $idEncarregado = EncarregadoController::storeEncarregado($dadosEncarregado);
            $id[$i] = $idEncarregado;
            if(!$id[$i])
            {
                $msg="Fique atento nos dados de identifcação, este candidato já está inscrito!";
                return redirect()->back()->with("ErroPessoa",$msg);
            }
        }

        $alunoEncarregado = [
            'encarregado' => $id,
            'aluno_id' => $alunoId
        ];
        $alunoEncs = AlunoEncarregadoController::store($alunoEncarregado);
        if(!$alunoEncs)
        {
            $msg="Fique atento nos dados de identifcação, este candidato já está inscrito!";
            return redirect()->back()->with("ErroPessoa",$msg);
        }

        $posicao = 0; // posição do caractere desejado(Onde começa a contagem do caracter)
        $abreNome = substr($request['nome_completo'], $posicao,2);
        $abreSobreNome = substr($request['nome_enc1'], $posicao,2);

        $dadosUser=[
            'nome_usuario'=>$abreNome.count(User::all()).$abreSobreNome,
            'email'=>$request['email'],
            'password'=>bcrypt($request['num_tel']),
            'cargo_usuario'=>'Aluno',
            'status_usuario'=>0,
            'pessoa_id'=>$pessoa_id,
        ];
        $user = UserController::store($dadosUser);
        if(!$user)
        {
            $msg="Fique atento nos dados de identifcação, este candidato já está inscrito!";
            return redirect()->back()->with("ErroPessoa",$msg);
        }

        CandidatoController::atualizarMatriculado($request);
        $msg="Candidato matriculado com sucesso!";
        return Redirect::route('inscricao-index')->with("Sucesso",$msg);
    }
}
