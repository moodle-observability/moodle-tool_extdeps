moodle-tool_extdeps
===================

**Monitor das dependências externas dos cursos do Moodle.**

Os cursos incorporam vídeos e pacotes que ficam em outros sites. Quando um vídeo vira privado ou é removido, ninguém
percebe até um estudante reclamar. O ``tool_extdeps`` encontra cada dependência externa de cada curso, verifica
periodicamente e registra quando ela quebra.

.. note::

   Versão 0.1.3 (alfa).

Recursos
--------

* **Descoberta**: resumo do curso, seções, descrição de toda atividade, páginas, livros, lições, URLs, questões e o
  conteúdo dos pacotes SCORM.
* **Provedores** (subplugins): YouTube, Vimeo, eduplay e SCORM. Uma verificação por dependência distinta, não importa
  quantos cursos a usam.
* **Sem alarme falso**: erro temporário e resposta desconhecida nunca confirmam falha; a falha exige N resultados
  iguais.
* **Leve para o site**: tarefas agendadas com orçamento de tempo; eventos só marcam cursos; nenhuma tarefa adhoc
  automática.
* **Análise sob demanda**: verifica agora uma atividade, um curso, um provedor ou o site, com a evidência de cada
  veredito, modo só relatar, modo estrito com segundo método e comparação com uma lista conferida à mão.
* **Integrações** (opcionais): avisos pelo ``local_alerthub``, métricas pelo ``tool_prometheus`` e relatórios no
  ``report_extdeps``.

Requisitos
----------

* Moodle 4.5 ou 5.3 (LTS).
* PostgreSQL ou MariaDB.

.. toctree::
   :maxdepth: 1

   installation
   configuration
   usage
