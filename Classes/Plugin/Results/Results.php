<?php
namespace ApacheSolrForTypo3\Solr\Plugin\Results;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\CommandResolver;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use ApacheSolrForTypo3\Solr\Plugin\CommandPluginBase;
use ApacheSolrForTypo3\Solr\Plugin\PluginCommand;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Template;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Plugin 'Solr Search' for the 'solr' extension.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class Results extends CommandPluginBase
{
    /**
     * Path to this script relative to the extension dir.
     *
     * @var string
     */
    public $scriptRelPath = 'Classes/Plugin/Results/Results.php';

    /**
     * @var SearchResultSet
     */
    protected $searchResultSet;

    /**
     * Perform the action for the plugin. In this case it calls the search()
     * method which internally performs the search.
     *
     * @return void
     */
    protected function performAction()
    {
        if ($this->getSearchResultSetService()->getIsSolrAvailable()) {
            $searchRequest = $this->buildSearchRequest();
            $this->searchResultSet = $this->getSearchResultSetService()->search($searchRequest);
        }
    }

    /**
     * @return SearchRequest
     */
    private function buildSearchRequest()
    {
        $solrParameters = [];
        $solrPostParameters = GeneralUtility::_POST('tx_solr');
        $solrGetParameters = GeneralUtility::_GET('tx_solr');

        // check for GET parameters, POST takes precedence
        if (isset($solrGetParameters) && is_array($solrGetParameters)) {
            $solrParameters = $solrGetParameters;
        }
        if (isset($solrPostParameters) && is_array($solrPostParameters)) {
            $solrParameters = $solrPostParameters;
        }

        /** @var $searchRequest SearchRequest */
        $searchRequest = GeneralUtility::makeInstance(
            SearchRequest::class,
            ['tx_solr' => $solrParameters],
            $GLOBALS['TSFE']->id,
            $GLOBALS['TSFE']->sys_language_uid,
            $this->typoScriptConfiguration
        );
        $searchRequest->mergeArguments(['tx_solr' => $this->piVars]);
        $searchRequest->mergeArguments(['q' => $this->getRawUserQuery()]);

        return $searchRequest;
    }

    /**
     * Implementation of preRender() method. Used to include CSS files.
     *
     */
    protected function preRender()
    {
        $resultsCss = $this->typoScriptConfiguration->getCssFileByFileKey('results');
        if ($resultsCss !== '') {
            $cssFile = GeneralUtility::createVersionNumberedFilename($GLOBALS['TSFE']->tmpl->getFileName($resultsCss));
            $GLOBALS['TSFE']->additionalHeaderData['tx_solr-resultsCss'] = '<link href="' . $cssFile . '" rel="stylesheet" type="text/css" />';
        }
    }

    /**
     * Returns an initialized CommandResolver.
     *
     */
    protected function getCommandResolver()
    {
        return GeneralUtility::makeInstance(CommandResolver::class);
    }

    /**
     * Retrieves the list of commands to process for the results view.
     *
     * @return array An array of command names to process for the result view
     */
    protected function getCommandList()
    {
        $requirements = PluginCommand::REQUIREMENT_NONE;

        if ($this->getSearchResultSetService()->getHasSearched()) {
            $requirements = PluginCommand::REQUIREMENT_HAS_SEARCHED;

            if ($this->searchResultSet->getUsedSearch()->getNumberOfResults() > 0) {
                $requirements += PluginCommand::REQUIREMENT_HAS_RESULTS;
            } else {
                $requirements += PluginCommand::REQUIREMENT_NO_RESULTS;
            }
        }

        return CommandResolver::getPluginCommands('results', $requirements);
    }

    /**
     * Performs post initialization.
     *
     */
    protected function postInitialize()
    {
        // disable caching
        $this->pi_USER_INT_obj = 1;
    }

    /**
     * Overrides certain TypoScript configuration options with their values
     * from FlexForms.
     *
     */
    protected function overrideTyposcriptWithFlexformSettings()
    {
        $flexFormConfiguration = [];

        // initialize with empty query, useful when no search has been
        // conducted yet but needs to show facets already.
        $initializeWithEmptyQuery = $this->getFlexFormValue('initializeWithEmptyQuery', 'sQuery');

        if (boolval($initializeWithEmptyQuery)) {
            $flexFormConfiguration['search.']['initializeWithEmptyQuery'] = 1;
        }

        $showResultsOfInitialEmptyQuery = $this->getFlexFormValue('showResultsOfInitialEmptyQuery', 'sQuery');
        if (boolval($showResultsOfInitialEmptyQuery)) {
            $flexFormConfiguration['search.']['showResultsOfInitialEmptyQuery'] = 1;
        }

        // initialize with non-empty query
        $initialQuery = trim((string)$this->getFlexFormValue('initializeWithQuery', 'sQuery'));
        if ($initialQuery !== '') {
            $flexFormConfiguration['search.']['initializeWithQuery'] = $initialQuery;
        }

        $showResultsOfInitialQuery = $this->getFlexFormValue('showResultsOfInitialQuery', 'sQuery');
        if (boolval($showResultsOfInitialQuery)) {
            $flexFormConfiguration['search.']['showResultsOfInitialQuery'] = 1;
        }

        // target page
        $flexformTargetPage = $this->getFlexFormValue('targetPage');
        if (!empty($flexformTargetPage)) {
            $flexFormConfiguration['search.']['targetPage'] = (int)$flexformTargetPage;
        }

        // boost function
        $boostFunction = trim((string)$this->getFlexFormValue('boostFunction', 'sQuery'));
        if ($boostFunction !== '') {
            $flexFormConfiguration['search.']['query.']['boostFunction'] = $boostFunction;
        }

        // boost query
        $boostQuery = trim((string)$this->getFlexFormValue('boostQuery', 'sQuery'));
        if ($boostQuery !== '') {
            $flexFormConfiguration['search.']['query.']['boostQuery'] = $boostQuery;
        }

        // sorting
        $flexformSorting = trim((string)$this->getFlexFormValue('sortBy', 'sQuery'));
        if ($flexformSorting !== '') {
            $flexFormConfiguration['search.']['query.']['sortBy'] = $flexformSorting;
        }

        // results per page
        $resultsPerPage = trim((string)$this->getFlexFormValue('resultsPerPage', 'sQuery'));
        if ($resultsPerPage !== '') {
            $flexFormConfiguration['search.']['results.']['resultsPerPage'] = intval($resultsPerPage);
        }

        // flexform overwrites _all_ filters set through TypoScript
        $flexformFilters = $this->getFlexFormValue('filter', 'sQuery');
        if (!empty($flexformFilters)) {
            $additionalFilters = GeneralUtility::trimExplode('|', $flexformFilters);

                // we keep the pageSections filter but replace all other filters
            $filterConfiguration = $this->typoScriptConfiguration->getSearchQueryFilterConfiguration();
            if (isset($filterConfiguration['__pageSections'])) {
                $additionalFilters['__pageSections'] = $filterConfiguration['__pageSections'];
            }
            $this->typoScriptConfiguration->setSearchQueryFilterConfiguration($additionalFilters);
        }

        $this->typoScriptConfiguration->mergeSolrConfiguration($flexFormConfiguration);
    }

    /**
     * Post initialization of the template engine, adding some Solr variables.
     *
     * @param Template $template The template object as initialized thus far.
     * @return Template The modified template instance with additional variables available for rendering.
     */
    protected function postInitializeTemplateEngine(Template $template)
    {
        $template->addVariable('tx_solr', $this->getSolrVariables());

        return $template;
    }

    /**
     * Gets a list of EXT:solr variables like the prefix ID.
     *
     * @return array array of EXT:solr variables
     */
    protected function getSolrVariables()
    {
        $currentUrl = $this->getCurrentUrlWithQueryLinkBuilder();

        return ['prefix' => $this->prefixId, 'query_parameter' => 'q', 'current_url' => $currentUrl, 'q' => $this->getCleanUserQuery()];
    }

    /**
     * Gets the plugin's configuration.
     *
     * @return TypoScriptConfiguration Configuration
     */
    public function getConfiguration()
    {
        return $this->typoScriptConfiguration;
    }

    /**
     * Returns the key which is used to determine the template file from the typoscript setup.
     *
     * @return string
     */
    protected function getTemplateFileKey()
    {
        return 'results';
    }

    /**
     * Returns the plugin key, used in various base methods.
     *
     * @return string
     */
    protected function getPluginKey()
    {
        return 'PiResults';
    }

    /**
     * Returns the main subpart to work on.
     *
     */
    protected function getSubpart()
    {
        return 'solr_search';
    }
}
