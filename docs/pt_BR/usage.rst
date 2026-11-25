Uso
===

As tarefas agendadas varrem os cursos marcados e verificam as dependências cujo prazo chegou. O resultado aparece nos
relatórios do ``report_extdeps`` e nas métricas ``moodle_tool_extdeps_*``.

Análise sob demanda pela linha de comando::

    php admin/tool/extdeps/cli/analyze.php --course=12
    php admin/tool/extdeps/cli/analyze.php --provider=youtube --dry-run --strict --compare=/tmp/quebrados.csv

A comparação mostra os falsos positivos, os falsos negativos, a precisão e a cobertura.
