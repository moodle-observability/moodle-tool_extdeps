Instalação
==========

1. Baixe o ZIP da última release e instale em *Administração do site > Plugins > Instalar plugins*, ou copie o código
   para ``admin/tool/extdeps`` (``public/admin/tool/extdeps`` no Moodle 5.1 ou superior).
2. Atualize o Moodle. Todos os cursos ficam marcados para a primeira varredura, que roda nas próximas execuções do
   cron.
3. Se quiser, instale o ``report_extdeps`` (telas), o ``local_alerthub`` (avisos) e o ``tool_prometheus`` (métricas).

As requisições HTTP saem do servidor do Moodle para os provedores (YouTube, Vimeo, eduplay), pelo proxy e pelos
hosts permitidos do site.
