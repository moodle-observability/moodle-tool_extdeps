moodle-tool_extdeps
===================

**Monitor of the external dependencies of the Moodle courses.**

Courses embed videos and packages that live elsewhere. When a video becomes private or is removed, nobody notices
until a student complains. ``tool_extdeps`` finds every external dependency of every course, checks it periodically
and records when it breaks.

.. note::

   Version 0.1.3 (alpha).

Features
--------

* **Discovery**: course summary, sections, the intro of every activity, pages, books, lessons, URLs, quiz questions
  and the content of SCORM packages.
* **Providers** (subplugins): YouTube, Vimeo, eduplay and SCORM. One check per distinct dependency, however many
  courses use it.
* **No false alarms**: temporary errors and unknown answers never confirm a failure; a failure needs N equal results.
* **Light on the site**: scheduled tasks with time budgets; events only flag courses; no automatic ad hoc tasks.
* **On demand analysis**: check an activity, a course, a provider or the whole site now, with the evidence of each
  verdict, a dry run, a strict mode with a second method and the comparison with a list checked by hand.
* **Integrations** (optional): alerts through ``local_alerthub``, metrics through ``tool_prometheus`` and reports in
  ``report_extdeps``.

Requirements
------------

* Moodle 4.5 or 5.3 (LTS).
* PostgreSQL or MariaDB.

.. toctree::
   :maxdepth: 1

   installation
   configuration
   usage
