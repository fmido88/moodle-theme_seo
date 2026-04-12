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
import Ajax from 'core/ajax';
import {exception} from 'core/notification';

export const init = function(publicContent = '', countrycode = null) {
    $(async function() {
        let keywords = document.querySelector("meta[name='keywords']")?.content || "";
        keywords = keywords.split(",").map((value) => value.trim());

        const contentJson = {
            title: document.title || "Untitled Page",
            // Get the content appeared to the crawler.
            htmlText: publicContent ?? $('[data-purpose="seo-page-content"]').html(),
            keyword: keywords.shift() ?? '',
            subKeywords: keywords,
            metaDescription: document.querySelector("meta[name='description']")?.content || "",
            languageCode: M.cfg.language,
            countryCode: countrycode ?? "us"
        };

        // Initialize SeoCheck with structured content
        let checker = new SeoCheck(contentJson, window.location.hostname, true);

        // Perform analysis
        let result = await checker.analyzeSeo();

        // Log the SEO report for debugging only.
        if (window.M.cfg.developerdebug) {
            // eslint-disable-next-line no-console
            console.log(contentJson, "SEO Analysis Report:", result);
        }
        let promiseLinksValidation = [
            fixLinks(result.internalLinks.unique),
            fixLinks(result.internalLinks.duplicate),
            fixLinks(result.outboundLinks.unique),
            fixLinks(result.outboundLinks.duplicate),
        ];

        promiseLinksValidation = validateLinks(promiseLinksValidation);

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
            externallinkscount:          result.outboundLinks.all.length,
            uniqueexternallinkscount:    result.outboundLinks.unique.length,
            duplicateexternallinkscount: result.outboundLinks.duplicate.length,
        };

        let html = await Templates.render("theme_seo/seo-info", context);

        $('.seo-info-details').html(html);

        let linksLists = {
            uniqueinternallinks:     promiseLinksValidation[0],
            duplicateinternallinks:  promiseLinksValidation[1],
            uniqueexternallinks:     promiseLinksValidation[2],
            duplicateexternallinks:  promiseLinksValidation[3],
        };
        for (let key in linksLists) {
            linksLists[key].then((list) => {
                let context = {
                    "links": list
                };
                return Templates.render("theme_seo/links-list", context);
            }).then((html) => {
                $('[data-placeholder="' + key + '"]').html(html);
                return;
            }).catch(exception);
        }
    });
};

/**
 * @param {Array<{text:String, href:String}>} links
 */
function fixLinks(links) {
    links = links.map((link) => {
        if (link.href.startsWith('#')) {
            link.href = window.location.href + link.href;
        }

        link.text = link.text.trim();
        if (!link.text) {
            link.text = link.href;
        }

        return link;
    });

    return links;
}
/**
 * @param {{text:String, href:String}[][]} links
 */
function validateLinks(links) {
    return links.map((linksSet) => {
        if (!linksSet.length) {
            let promise = new Promise(function(resolve) {
                resolve(linksSet);
            });
            return promise;
        }

        return Ajax.call([{
            methodname: 'theme_seo_validate_link',
            args: {
                links: linksSet
            }
        }])[0];
    });
}