Usage
=====

The scheduled tasks scan the flagged courses and check the dependencies that are due. The results are in the reports
of ``report_extdeps`` and in the metrics ``moodle_tool_extdeps_*``.

On demand analysis from the command line::

    php admin/tool/extdeps/cli/analyze.php --course=12
    php admin/tool/extdeps/cli/analyze.php --provider=youtube --dry-run --strict --compare=/tmp/known-broken.csv

The comparison prints the false positives, the false negatives, the precision and the recall.
