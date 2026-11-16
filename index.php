<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrição - Acampamento</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px; /* Padronizado com o form */
            background: #ffffff;
            padding: 30px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }

        /* Menu superior de navegação limpo idêntico ao dashboard */
        .top-nav {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 20px;
        }
        .top-nav a {
            background-color: #e2e8f0;
            color: #334155;
            padding: 8px 14px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            transition: background 0.2s;
        }
        .top-nav a:hover, .top-nav a.active {
            background-color: #cbd5e1;
            color: #0f172a;
        }

        .form-group {
            margin-bottom: 20px;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="text"],
        input[type="email"],
        input[type="date"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #ffffff;
        }
        input:read-only {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 5px;
        }

        /* Estilo moderno em formato de botões para os tamanhos de camiseta */
        .tamanho-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }
        .tamanho-item {
            flex: 1;
            min-width: 55px;
        }
        .tamanho-item input[type="checkbox"] {
            display: none;
        }
        .tamanho-item label {
            display: block;
            text-align: center;
            padding: 10px 0;
            border: 2px solid #cbd5e1;
            border-radius: 6px;
            background-color: #f8fafc;
            color: #334155;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .tamanho-item input[type="checkbox"]:checked + label {
            background-color: #dc2626;
            border-color: #b91c1c;
            color: #ffffff;
            box-shadow: 0 2px 4px rgba(220, 38, 38, 0.2);
        }
        .tamanho-item label:hover {
            border-color: #94a3b8;
            background-color: #f1f5f9;
        }
        .tamanho-item input[type="checkbox"]:checked + label:hover {
            background-color: #b91c1c;
        }

        .conditional-field {
            margin-top: 10px;
            padding-left: 15px;
            border-left: 3px solid #dc2626;
        }
        button {
            width: 100%;
            background-color: #28a745;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>

<div class="container">

    <div class="top-nav">
        <a href="home.php">Início</a>
        <a href="index.php" class="active">Nova Inscrição</a>
        <a href="dashboard.php">Painel Geral</a>
        <a href="comunicacao.php">Comunicação</a>
        <a href="buscar_mala.php">Malas</a>
        <a href="logout.php" class="logout">Sair</a>
    </div>

    <h2>Formulário de Inscrição</h2>
    
    <form id="formInscricao" action="processa.php" method="POST" onsubmit="return confirmarEnvio()">
        
        <div class="form-group">
            <label for="nome">Nome *</label>
            <input type="text" id="nome" name="nome" placeholder="Insira seu nome" required>
        </div>

        <div class="form-group">
            <label for="cpf">CPF:</label>
            <input type="text" id="cpf" name="cpf" maxlength="14" oninput="mascaraCPF(this)" required placeholder="000.000.000-00">
        </div>

        <div class="form-group">
            <label>Gênero *</label>
            <div class="radio-group">
                <label><input type="radio" name="genero" value="Mulher" required> Mulher</label>
                <label><input type="radio" name="genero" value="Homem"> Homem</label>
            </div>
        </div>

        <div class="form-group">
            <label for="data_nascimento">Data de Aniversário *</label>
            <input type="date" id="data_nascimento" name="data_nascimento" required>
        </div>

        <div class="form-group">
            <label for="telefone">Telefone *</label>
            <input type="text" id="telefone" name="telefone" maxlength="15" oninput="mascaraTelefone(this)" placeholder="(00) 00000-0000" required>
        </div>

        <div class="form-group">
            <label for="email">E-mail *</label>
            <input type="email" id="email" name="email" placeholder="Insira seu email" required>
        </div>

        <!-- Bloco de Endereço com Busca Automática por CEP -->
        <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">
        <h3 style="font-size: 16px; color: #444; margin-bottom: 15px;">Endereço</h3>

        <div class="form-row">
            <div class="form-group" style="flex: 1;">
                <label for="cep">CEP *</label>
                <input type="text" id="cep" name="cep" maxlength="9" oninput="mascaraCEP(this)" onblur="pesquisarCEP(this.value)" placeholder="00000-000" required>
            </div>
            <div class="form-group" style="flex: 2;">
                <label for="rua">Rua / Logradouro *</label>
                <input type="text" id="rua" name="rua" placeholder="Preenchimento automático" required readonly>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex: 1;">
                <label for="numero">Número *</label>
                <input type="text" id="numero" name="numero" placeholder="Ex: 123" required>
            </div>
            <div class="form-group" style="flex: 1;">
                <label for="complemento">Complemento</label>
                <input type="text" id="complemento" name="complemento" placeholder="Apt, Bloco, Casa 2">
            </div>
            <div class="form-group" style="flex: 2;">
                <label for="bairro">Bairro *</label>
                <input type="text" id="bairro" name="bairro" placeholder="Preenchimento automático" required readonly>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex: 2;">
                <label for="cidade">Cidade *</label>
                <input type="text" id="cidade" name="cidade" placeholder="Preenchimento automático" required readonly>
            </div>
            <div class="form-group" style="flex: 1;">
                <label for="estado">Estado (UF) *</label>
                <input type="text" id="estado" name="estado" placeholder="UF" required readonly>
            </div>
        </div>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">

        <div class="form-group">
            <label>Faz uso de medicamento controlado?</label>
            <div class="radio-group">
                <label><input type="radio" name="medicamento_controlado" value="Sim" required> Sim</label>
                <label><input type="radio" name="medicamento_controlado" value="Não"> Não</label>
            </div>
        </div>

        <div class="form-group">
            <label>Possui alguma restrição alimentar?</label>
            <div class="radio-group">
                <label><input type="radio" name="restricao_alimentar" value="Sim" onclick="toggleCampo('restricao', true)" required> Sim</label>
                <label><input type="radio" name="restricao_alimentar" value="Não" onclick="toggleCampo('restricao', false)"> Não</label>
            </div>
            <div id="grupo_descricao_alimentar" class="conditional-field" style="display: none;">
                <label for="descricao_alimentar">Qual restrição alimentar?</label>
                <input type="text" id="descricao_alimentar" name="descricao_alimentar" placeholder="Ex: Intolerância a lactose, vegetariano...">
            </div>
        </div>

        <div class="form-group">
            <label>Possui alguma alergia?</label>
            <div class="radio-group">
                <label><input type="radio" name="alergia" value="Sim" onclick="toggleCampo('alergia', true)" required> Sim</label>
                <label><input type="radio" name="alergia" value="Não" onclick="toggleCampo('alergia', false)"> Não</label>
            </div>
            <div id="grupo_descricao_alergia" class="conditional-field" style="display: none;">
                <label for="descricao_alergia">Qual alergia?</label>
                <input type="text" id="descricao_alergia" name="descricao_alergia" placeholder="Ex: Alergia a dipirona, camarão...">
            </div>
        </div>

        <!-- Grid em estilo de botões para o tamanho da camiseta -->
        <div class="form-group">
            <label>Tamanho da Camiseta *</label>
            <div class="tamanho-grid">
                <div class="tamanho-item">
                    <input type="checkbox" id="tam_p" name="tamanho_camiseta" value="P" onclick="selecionarTamanho(this)">
                    <label for="tam_p">P</label>
                </div>
                <div class="tamanho-item">
                    <input type="checkbox" id="tam_m" name="tamanho_camiseta" value="M" onclick="selecionarTamanho(this)">
                    <label for="tam_m">M</label>
                </div>
                <div class="tamanho-item">
                    <input type="checkbox" id="tam_g" name="tamanho_camiseta" value="G" onclick="selecionarTamanho(this)">
                    <label for="tam_g">G</label>
                </div>
                <div class="tamanho-item">
                    <input type="checkbox" id="tam_gg" name="tamanho_camiseta" value="GG" onclick="selecionarTamanho(this)">
                    <label for="tam_gg">GG</label>
                </div>
                <div class="tamanho-item">
                    <input type="checkbox" id="tam_g1" name="tamanho_camiseta" value="G1" onclick="selecionarTamanho(this)">
                    <label for="tam_g1">G1</label>
                </div>
                <div class="tamanho-item">
                    <input type="checkbox" id="tam_g2" name="tamanho_camiseta" value="G2" onclick="selecionarTamanho(this)">
                    <label for="tam_g2">G2</label>
                </div>
                <div class="tamanho-item">
                    <input type="checkbox" id="tam_g3" name="tamanho_camiseta" value="G3" onclick="selecionarTamanho(this)">
                    <label for="tam_g3">G3</label>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="contato_emergencia">Contato de Emergência *</label>
            <input type="text" id="contato_emergencia" name="contato_emergencia" maxlength="15" oninput="mascaraTelefone(this)" placeholder="(00) 00000-0000" required>
        </div>

        <div class="form-group">
            <label for="nome_contato_emergencia">Nome do Contato de Emergência *</label>
            <input type="text" id="nome_contato_emergencia" name="nome_contato_emergencia" placeholder="Insira o nome do contato" required>
        </div>

        <button type="submit">Enviar</button>

    </form>
</div>

<!-- SCRIPTS DE MÁSCARAS, BUSCA DE CEP, CAMPOS CONDICIONAIS E PROTEÇÃO -->
<script>
    // Máscara de CPF
    function mascaraCPF(input) {
        let valor = input.value.replace(/\D/g, "");
        if (valor.length > 11) valor = valor.substring(0, 11);
        valor = valor.replace(/(\d{3})(\d)/, "$1.$2");
        valor = valor.replace(/(\d{3})(\d)/, "$1.$2");
        valor = valor.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
        input.value = valor;
    }

    // Máscara de Telefone
    function mascaraTelefone(input) {
        let valor = input.value.replace(/\D/g, "");
        if (valor.length > 11) valor = valor.substring(0, 11);

        if (valor.length > 10) {
            valor = valor.replace(/^(\d{2})(\d{5})(\d{4}).*/, "($1) $2-$3");
        } else if (valor.length > 5) {
            valor = valor.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, "($1) $2-$3");
        } else if (valor.length > 2) {
            valor = valor.replace(/^(\d{2})(\d{0,5})/, "($1) $2");
        } else if (valor.length > 0) {
            valor = valor.replace(/^(\d*)/, "($1");
        }
        input.value = valor;
    }

    // Máscara de CEP
    function mascaraCEP(input) {
        let valor = input.value.replace(/\D/g, "");
        if (valor.length > 8) valor = valor.substring(0, 8);
        valor = valor.replace(/^(\d{5})(\d)/, "$1-$2");
        input.value = valor;
    }

    // Consulta CEP ViaCEP
    function pesquisarCEP(valor) {
        let cep = valor.replace(/\D/g, '');
        if (cep.length === 8) {
            let url = `https://viacep.com.br/ws/${cep}/json/`;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (!data.erro) {
                        document.getElementById('rua').value = data.logradouro;
                        document.getElementById('bairro').value = data.bairro;
                        document.getElementById('cidade').value = data.localidade;
                        document.getElementById('estado').value = data.uf;
                        document.getElementById('numero').focus();
                    } else {
                        alert("CEP não encontrado.");
                        limparCamposEndereco();
                    }
                })
                .catch(error => console.error("Erro ao buscar CEP:", error));
        }
    }

    function limparCamposEndereco() {
        document.getElementById('rua').value = '';
        document.getElementById('bairro').value = '';
        document.getElementById('cidade').value = '';
        document.getElementById('estado').value = '';
    }

    // Função para exibir/ocultar os campos de descrição dinamicamente
    function toggleCampo(tipo, mostrar) {
        let grupo = document.getElementById('grupo_descricao_' + (tipo === 'restricao' ? 'alimentar' : 'alergia'));
        let input = document.getElementById(tipo === 'restricao' ? 'descricao_alimentar' : 'descricao_alergia');

        if (mostrar) {
            grupo.style.display = 'block';
            input.required = true;
        } else {
            grupo.style.display = 'none';
            input.required = false;
            input.value = '';
        }
    }

    // Garante seleção única de tamanho de camiseta com os checkboxes em formato de botão
    function selecionarTamanho(checkboxAtual) {
        let checkboxes = document.querySelectorAll('input[name="tamanho_camiseta"]');
        
        checkboxes.forEach(cb => {
            if (cb !== checkboxAtual) {
                cb.checked = false;
                cb.required = false;
            }
        });

        if (checkboxAtual.checked) {
            checkboxes.forEach(cb => cb.required = false);
        } else {
            checkboxes.forEach(cb => cb.required = true);
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        let checkboxes = document.querySelectorAll('input[name="tamanho_camiseta"]');
        checkboxes.forEach(cb => cb.required = true);
    });

    // Caixa de confirmação antes de enviar o formulário
    function confirmarEnvio() {
        return confirm("Deseja realmente finalizar e enviar a sua inscrição?");
    }

    // Proteção contra perda de dados ao atualizar a página
    let formModificado = false;
    let enviadoComSucesso = false;

    const formulario = document.getElementById('formInscricao');
    formulario.addEventListener('input', () => {
        formModificado = true;
    });

    formulario.addEventListener('submit', () => {
        enviadoComSucesso = true;
    });

    window.addEventListener('beforeunload', (evento) => {
        if (formModificado && !enviadoComSucesso) {
            evento.preventDefault();
            evento.returnValue = '';
        }
    });
</script>

</body>
</html>