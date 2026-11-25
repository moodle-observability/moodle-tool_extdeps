<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Brazilian Portuguese strings for tool_extdeps.
 *
 * @package    tool_extdeps
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['advice_corrupted'] = 'O arquivo do pacote está danificado. Envie o pacote novamente.';
$string['advice_embed_blocked'] = 'O dono não permite incorporar este conteúdo em outros sites. Peça ao dono que permita a incorporação, ou troque por um link ou outra cópia.';
$string['advice_misconfigured'] = 'A atividade aponta para uma ferramenta apagada ou desativada. Escolha outra ferramenta ou fale com o administrador.';
$string['advice_missing'] = 'O arquivo do pacote não existe mais. Envie o pacote novamente.';
$string['advice_private'] = 'O conteúdo ficou privado. Peça ao dono que o torne público ou não listado, ou troque-o.';
$string['advice_region_blocked'] = 'O conteúdo está bloqueado no Brasil. Troque por outra cópia.';
$string['advice_removed'] = 'O conteúdo foi removido ou nunca existiu. Troque por outra cópia ou retire a referência.';
$string['advice_restricted'] = 'O conteúdo exige login ou confirmação de idade, então o aluno não consegue assistir incorporado. Troque-o ou peça ao dono que retire a restrição.';
$string['advice_tls_expiring'] = 'O certificado do servidor externo está para vencer. Avise o responsável pelo servidor.';
$string['advice_tls_invalid'] = 'O certificado do servidor externo é inválido. Avise o responsável pelo servidor.';
$string['advice_unavailable'] = 'O servidor externo não responde ou responde com erro. Confira o endereço; se persistir, troque-o.';
$string['alert_body'] = 'A dependência externa {$a->url} ({$a->provider}) usada no curso "{$a->course}" está agora: {$a->status}.';
$string['alert_title'] = 'Dependência externa quebrada em {$a->course}: {$a->status}';
$string['alertheading'] = 'Avisos';
$string['alertheading_desc'] = 'Os avisos são enviados pelo local_alerthub, quando ele está instalado.';
$string['alertstatuses'] = 'Situações que geram aviso';
$string['alertstatuses_desc'] = 'Situações confirmadas que geram um aviso para o curso.';
$string['confirmations'] = 'Confirmações';
$string['confirmations_desc'] = 'Quantas falhas iguais seguidas confirmam a quebra. Uma evidência definitiva (por exemplo, uma API oficial) confirma na hora.';
$string['enabled'] = 'Habilitado';
$string['enabled_desc'] = 'Se este provedor encontra e verifica dependências.';
$string['endedcategories'] = 'Categorias de cursos encerrados';
$string['endedcategories_desc'] = 'Ids de categorias, separados por vírgula, cujos cursos são tratados como encerrados (inclui subcategorias).';
$string['errorcomparecsv'] = 'O CSV precisa de cabeçalho com a coluna key (e, opcionalmente, provider) ou video_id (e, opcionalmente, plataforma).';
$string['errorunknownprovider'] = 'Provedor desconhecido: {$a}';
$string['excludedcategories'] = 'Categorias excluídas';
$string['excludedcategories_desc'] = 'Ids de categorias, separados por vírgula, nunca monitoradas (inclui subcategorias).';
$string['excludedcourses'] = 'Cursos excluídos';
$string['excludedcourses_desc'] = 'Ids de cursos, separados por vírgula, nunca monitorados.';
$string['extdeps:analyze'] = 'Executar análises sob demanda de dependências externas';
$string['extdeps:manage'] = 'Gerenciar o monitoramento de dependências externas';
$string['generalsettings'] = 'Configurações gerais';
$string['historyretention'] = 'Retenção do histórico';
$string['historyretention_desc'] = 'Por quanto tempo o histórico de mudanças de situação e as análises sob demanda são mantidos.';
$string['includeended'] = 'Incluir cursos encerrados';
$string['includeended_desc'] = 'Monitorar cursos com data de término vencida e cursos das categorias de encerrados.';
$string['includehidden'] = 'Incluir cursos ocultos';
$string['includehidden_desc'] = 'Monitorar cursos ocultos para os alunos.';
$string['includehiddencm'] = 'Incluir atividades ocultas';
$string['includehiddencm_desc'] = 'Monitorar atividades ocultas para os alunos.';
$string['intervalbroken'] = 'Nova verificação de quebradas';
$string['intervalbroken_desc'] = 'Tempo até verificar de novo uma dependência confirmada como quebrada.';
$string['intervalok'] = 'Nova verificação de disponíveis';
$string['intervalok_desc'] = 'Tempo até verificar de novo uma dependência disponível.';
$string['intervalsuspect'] = 'Nova verificação de suspeitas';
$string['intervalsuspect_desc'] = 'Tempo até verificar de novo uma dependência com falha não confirmada ou resposta inconclusiva.';
$string['loadheading'] = 'Carga';
$string['loadheading_desc'] = 'Limites que protegem o site e os provedores externos.';
$string['orphanretention'] = 'Retenção de órfãs';
$string['orphanretention_desc'] = 'Por quanto tempo uma dependência que nenhum curso usa mais é mantida.';
$string['packagebytebudget'] = 'Bytes de pacotes por execução (MB)';
$string['packagebytebudget_desc'] = 'Máximo de megabytes de conteúdo de pacotes lidos em cada execução da tarefa de pacotes.';
$string['packagetimebudget'] = 'Tempo de pacotes por execução (segundos)';
$string['packagetimebudget_desc'] = 'Máximo de segundos de cada execução da tarefa de pacotes.';
$string['pluginname'] = 'Monitor de dependências externas';
$string['privacy:metadata:exception'] = 'Falsos positivos aceitos pelos gestores.';
$string['privacy:metadata:exception:reason'] = 'Justificativa informada.';
$string['privacy:metadata:exception:timecreated'] = 'Quando foi aceito.';
$string['privacy:metadata:exception:userid'] = 'Quem aceitou.';
$string['privacy:metadata:run'] = 'Análises sob demanda.';
$string['privacy:metadata:run:scope'] = 'O que foi analisado.';
$string['privacy:metadata:run:timequeued'] = 'Quando foi pedida.';
$string['privacy:metadata:run:userid'] = 'Quem pediu.';
$string['rate'] = 'Requisições por minuto';
$string['rate_desc'] = 'Máximo de requisições por minuto enviadas a este provedor.';
$string['scanbudget'] = 'Tempo de varredura por execução (segundos)';
$string['scanbudget_desc'] = 'Máximo de segundos de cada execução da tarefa que varre os cursos alterados.';
$string['scopeheading'] = 'O que é monitorado';
$string['scopeheading_desc'] = 'O plugin só lê os cursos: ele nunca altera o conteúdo deles.';
$string['status_corrupted'] = 'Corrompido';
$string['status_embed_blocked'] = 'Incorporação bloqueada';
$string['status_misconfigured'] = 'Configuração inválida';
$string['status_missing'] = 'Ausente';
$string['status_ok'] = 'Disponível';
$string['status_pending'] = 'Ainda não verificado';
$string['status_private'] = 'Privado';
$string['status_region_blocked'] = 'Bloqueado no país';
$string['status_removed'] = 'Removido';
$string['status_restricted'] = 'Restrito (login ou idade)';
$string['status_temporary_error'] = 'Erro temporário';
$string['status_tls_expiring'] = 'Certificado vencendo';
$string['status_tls_invalid'] = 'Certificado inválido';
$string['status_unavailable'] = 'Indisponível';
$string['status_unknown'] = 'Desconhecido';
$string['subplugintype_extdepsprovider'] = 'Provedor';
$string['subplugintype_extdepsprovider_plural'] = 'Provedores';
$string['task_cleanup'] = 'Limpar dependências externas';
$string['task_full_sweep'] = 'Marcar cursos para nova varredura de dependências externas';
$string['task_run_analysis'] = 'Análise sob demanda de dependências externas';
$string['task_scan_dirty_courses'] = 'Varrer cursos alterados em busca de dependências externas';
$string['task_scan_packages'] = 'Ler pacotes em busca de dependências externas';
$string['task_verify_resources'] = 'Verificar dependências externas';
$string['timeout'] = 'Tempo limite (segundos)';
$string['timeout_desc'] = 'Tempo limite de cada requisição a este provedor.';
$string['type_extdepsprovider'] = 'Provedor';
$string['type_extdepsprovider_plural'] = 'Provedores';
$string['verdict_confirmed_ok'] = 'Disponibilidade confirmada';
$string['verdict_divergent'] = 'Métodos divergem';
$string['verdict_inconclusive'] = 'Inconclusivo';
$string['verdict_removed_from_content'] = 'Removido do conteúdo';
$string['verdict_still_broken'] = 'Continua quebrado';
$string['verdict_suspected'] = 'Falha ainda não confirmada';
$string['verifybudget'] = 'Tempo de verificação por execução (segundos)';
$string['verifybudget_desc'] = 'Máximo de segundos de cada execução da tarefa de verificação, dividido entre os provedores.';
