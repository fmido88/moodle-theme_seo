// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/* eslint-disable no-console */

/**
 * Analyze the page content to calculate the SEO score and
 * display the data and problems to the admin.
 *
 * @module     theme_seo/analyze
 * @copyright  2025 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import SeoCheck from "theme_seo/seord";
import $ from "jquery";
import Templates from "core/templates";

export const init = function(publicPage = true, redirected = false) {
    $(async function() {
        let toogelTimeout;

        $('.seo-manager-footer__toggle').on('click', function() {
            clearTimeout(toogelTimeout);
            $('.seo-manager-footer__content').slideToggle();

            toogelTimeout = setTimeout(() => {
                let isVisible = $('.seo-manager-footer__content').is(':visible');
                $(this).html(isVisible ? '&times;' : '&#9650;');
            }, 500);
        });

        if (!publicPage || redirected) {
            return;
        }

        let keywords = document.querySelector("meta[name='keywords']")?.content || "";
        keywords = keywords.split(",").map((value) => value.trim());

        const contentJson = {
            title: document.title || "Untitled Page",
            // Get the content appeared to the crawler.
            htmlText: $('[data-purpose="seo-page-content"]').html(),
            keyword: keywords.shift() ?? '',
            subKeywords: keywords,
            metaDescription: document.querySelector("meta[name='description']")?.content || "",
            languageCode: M.cfg.language,
            // countryCode: "us"
        };

        // Initialize SeoCheck with structured content
        let checker = new SeoCheck(contentJson, window.location.hostname, true);

        // Perform analysis
        let result = await checker.analyzeSeo();

        // Log the SEO report for debugging only.
        // console.log("SEO Analysis Report:", result);
        // Display the SEO report
        let context = {
            seoscore:                    result.seoScore,
            wordcount:                   result.wordCount,
            keywordseoscore:             result.keywordSeoScore,
            keyworddensity:              result.keywordDensity,
            keywordfrequency:            result.keywordFrequency,
            pagetitle:                   contentJson.title,
            metadescription:             contentJson.metaDescription,
            keyword:                     contentJson.keyword,
            subkeywords:                 contentJson.subKeywords.join(", "),
            titlewordcount:              result.titleSEO.wordCount,
            titlekeyworddensity:         result.titleSEO.keywordWithTitle.density,
            titlekeywordposition:        result.titleSEO.keywordWithTitle.position,
            titlekeyword:                result.titleSEO.keywordWithTitle.keyword,
            subkeywordswithtitle:        result.titleSEO.subKeywordsWithTitle,
            warning:                     result.messages.warnings,
            goodpoints:                  result.messages.goodPoints,
            minorwarnings:               result.messages.minorWarnings,
            totallinks:                  result.totalLinks,
            internallinkscount:          result.internalLinks.all.length,
            uniqueinternallinkscount:    result.internalLinks.unique.length,
            duplicateinternallinkscount: result.internalLinks.duplicate.length,
            uniqueinternallinks:         result.internalLinks.unique,
            duplicateinternallinks:      result.internalLinks.duplicate,
            externallinkscount:          result.outboundLinks.all.length,
            uniqueexternallinkscount:    result.outboundLinks.unique.length,
            duplicateexternallinkscount: result.outboundLinks.duplicate.length,
            uniqueexternallinks:         result.outboundLinks.unique,
            duplicateexternallinks:      result.outboundLinks.duplicate,
        };

        let html = await Templates.render("theme_seo/seo-info", context);

        $('.seo-info-details').html(html);
    });
};
