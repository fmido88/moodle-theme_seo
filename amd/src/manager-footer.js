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
 * TODO describe module manager-footer
 *
 * @module     theme_seo/manager-footer
 * @copyright  2026 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Ajax from 'core/ajax';
import $ from 'jquery';
import Template from 'core/templates';
import {init as analyzerInit} from './analyze';
import {exception} from 'core/notification';
import {getString} from 'core/str';

export const init = function(contextId = null, countrycode = null) {
    $(function() {
        let returnData;
        let requests = Ajax.call([{
            methodname: 'theme_seo_manage',
            args: {
                "url": window.location.href,
                "contextid": contextId ?? window.M.cfg.contextid
            }
        }]);

        requests[0].then((data) => {
            if (data === null) {
                return ['', ''];
            }
            returnData = data;
            return Template.render('theme_seo/manager_footer', data);
        })
        .then((html, js) => {
            if (!returnData) {
                return -1;
            }

            let placeHolder = $('div[data-for="theme-seo-manager-place-holder"]');

            placeHolder.html(html);
            Template.runTemplateJS(js);
            return getString('seotoggler', 'theme_seo');
        })
        .then((togglerText) => {
            if (togglerText === -1) {
                return;
            }

            let seoFooter = $('.seo-manager-footer');
            let toggler = $('.seo-manager-footer__toggle');
            let footerContent = $('.seo-manager-footer__content');
            let toogelTimeout;

            toggler.on('click', function(e) {
                e.stopPropagation();
                e.stopImmediatePropagation();
                e.preventDefault();

                clearTimeout(toogelTimeout);
                footerContent.slideToggle();

                toogelTimeout = setTimeout(() => {
                    let isVisible = footerContent.is(':visible');
                    if (isVisible) {
                        footerContent.trigger('focus');
                    }
                    $(this).html(isVisible ? '&times;' : togglerText);
                }, 500);
            });

            $(document).on('click', function(e) {

                let $target = $(e.target);
                if ($target.closest(seoFooter).length) {
                    return;
                }

                clearTimeout(toogelTimeout);

                footerContent.slideUp();
                toogelTimeout = setTimeout(() => {
                    toggler.html(togglerText);
                }, 500);
            });

            if (returnData.public && !returnData.redirected) {
                analyzerInit(returnData.content, countrycode);
            }

            return;
        })
        .catch(exception);
    });
};
