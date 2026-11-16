<?php
session_start();

// Se o usuário não estiver logado, redireciona para a tela de login
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    header("Location: login.php");
    exit();
}

include("conexao.php");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Início - Acampamento "No Limite com Cristo"</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1200px;
            background: #ffffff;
            padding: 30px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        /* Barra de Navegação Superior (Padrão do restante do sistema) */
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
        /* Estilo para o botão Sair */
        .top-nav a.logout {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .top-nav a.logout:hover {
            background-color: #fecaca;
        }

        /* Banner de Boas-Vindas Ampliado (Sem botão) */
        .hero-banner {
            background: #0f172a;
            color: #ffffff;
            padding: 65px 30px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: inset 0 0 20px rgba(0,0,0,0.5);
        }
        .hero-banner h3 {
            font-size: 18px;
            letter-spacing: 1.5px;
            color: #94a3b8;
            margin: 0 0 15px 0;
            text-transform: uppercase;
        }
        .hero-banner h1 {
            font-size: 36px;
            font-weight: 800;
            margin: 0;
            line-height: 1.35;
            letter-spacing: -0.5px;
        }

        /* Seção de Atalhos Rápidos para os Módulos */
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }

        .nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .nav-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 20px;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .nav-card:hover {
            border-color: #cbd5e1;
            background: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
            transform: translateY(-2px);
        }
        .nav-card-title {
            font-size: 15px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .nav-card-desc {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
            margin-bottom: 15px;
        }

        /* Botão Azul Padronizado */
        .btn-primary {
            display: block;
            width: 100%;
            background: #3b82f6;
            color: #ffffff;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s;
            box-sizing: border-box;
        }
        .btn-primary:hover {
            background: #2563eb;
        }

        /* Rodapé Institucional */
        .system-footer {
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #eee;
            padding-top: 20px;
            margin-top: 25px;
        }
    </style>
</head>
<body>

<div class="container">

    <!-- Top Nav Integrado -->
    <div class="top-nav">
        <a href="home.php" class="active">Início</a>
        <a href="index.php">Nova Inscrição</a>
        <a href="dashboard.php">Painel Geral</a>
        <a href="comunicacao.php">Comunicação</a>
        <a href="buscar_mala.php">Malas</a>
        <a href="logout.php" class="logout">Sair</a>
    </div>

    <!-- Banner de Boas-Vindas Ampliado -->
    <div class="hero-banner">
        <h3>Seja bem-vindo(a), <?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></h3>
        <h1>VAMOS FAZER O CÉU<br>ACONTECER POR AQUI?</h1>
    </div>
    
    <!-- Seção de Acesso aos Módulos -->
    <div id="modulos">
        <div class="section-title">Acesso Rápido aos Módulos</div>
        
        <div class="nav-grid">
            <!-- Módulo 1 -->
            <div class="nav-card">
                <div>
                    <div class="nav-card-title">📝 Nova Inscrição</div>
                    <div class="nav-card-desc">Cadastro para novos campistas com validação de CPF e integração Asaas.</div>
                </div>
                <a href="index.php" class="btn-primary">Acessar</a>
            </div>

            <!-- Módulo 2 -->
            <div class="nav-card">
                <div>
                    <div class="nav-card-title">📊 Painel Geral</div>
                    <div class="nav-card-desc">Métricas, controle de camisas, restrições médicas e base de dados.</div>
                </div>
                <a href="dashboard.php" class="btn-primary">Acessar</a>
            </div>

            <!-- Módulo 3 -->
            <div class="nav-card">
                <div>
                    <div class="nav-card-title">💬 Comunicação</div>
                    <div class="nav-card-desc">Central de avisos e disparos de mensagens automáticas via WhatsApp.</div>
                </div>
                <a href="comunicacao.php" class="btn-primary">Acessar</a>
            </div>

            <!-- Módulo 4 -->
            <div class="nav-card">
                <div>
                    <div class="nav-card-title">🧳 Rastreio de Malas</div>
                    <div class="nav-card-desc">Consulta por QR Code, classes (A-E) e impressão de etiquetas.</div>
                </div>
                <a href="buscar_mala.php" class="btn-primary">Acessar</a>
            </div>
        </div>
    </div>

    <!-- Rodapé -->
    <div class="system-footer">
        Trabalho de Conclusão de Curso (TCC) • Organização Paranaense de Ensino Técnico
    </div>

</div>

</body>
</html>

<?php 
if (isset($conn)) {
    $conn->close(); 
}
?>