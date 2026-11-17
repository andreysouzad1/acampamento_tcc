# tcc_acampamento

## Acesso interno e administração

- Não há auto-cadastro público. `cadastro.php` encaminha somente SuperAdmins para o controle de usuários.
- No primeiro acesso, a inicialização cria/migra as colunas de autenticação, cria a tabela de auditoria e semeia `admin` (perfil `SuperAdmin`) e `administrador` (perfil `AdminComum`). Defina `SUPERADMIN_SEED_PASSWORD` no ambiente do PHP antes de inicializar um banco novo. As senhas são armazenadas com `password_hash` e ambas as contas iniciais são obrigadas a definir uma nova senha no primeiro login.
- `admin_usuarios.php` permite ao SuperAdmin criar contas (senha inicial mínima de 12 caracteres) e ativar/inativar usuários sem apagar registros. `logs_auditoria.php` lista os eventos de login e as alterações administrativas.
- Sessões expiram após 30 minutos de inatividade; formulários com alterações usam token CSRF. A auditoria é append-only, com triggers no banco que recusam atualização e exclusão.
- Para abrir a plataforma, configure o banco definido em `conexao.php` e acesse `login.php`. Use HTTPS em implantação real, restrinja os privilégios do usuário do banco e defina processo institucional de retenção, acesso e atendimento aos direitos dos titulares; estes controles técnicos, por si só, não constituem certificação de conformidade jurídica com a LGPD.

### Especificação da tela de login

- Layout responsivo, minimalista e acessível em cinza, branco e azul-marinho, sem cadastro público ou login social.
- Identificação aceita usuário corporativo (`a-z`, números, ponto e sublinhado) ou e-mail; 4–254 caracteres, normalização para minúsculas e rejeição de espaços.
- Senha com controle mostrar/ocultar e mensagens de falha uniformes para reduzir enumeração de contas.
- Rodapé comunica que o acesso é restrito e monitorado conforme a LGPD.