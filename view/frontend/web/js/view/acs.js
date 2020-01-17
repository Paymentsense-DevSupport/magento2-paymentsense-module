/*
 * Copyright (C) 2020 Paymentsense Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 3
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      Paymentsense
 * @copyright   2020 Paymentsense Ltd.
 * @license     https://www.gnu.org/licenses/gpl-3.0.html
 */

define(
    [
        "jquery",
        'uiComponent'
    ],
    function ($) {
        'use strict';

        buildAuthForm();

        /**
         * Builds a form for 3-D Secure authentication
         *
         * Retrieves the data obtained by the data provider and redirects the customer to the ACS
         */
        function buildAuthForm()
        {
            createRedirectFrame("contentDiv");
            $.ajax({
                url: psAcsData.dataProviderUrl,
                type: "POST",
                success: sendRequest,
                error: showError
            });
        }

        /**
         * Sends requests to the ACS
         */
        function sendRequest(data)
        {
            var form = document.createElement("form");
            var elements = data.elements;
            var actionUrl = psAcsData.cancelActionUrl;
            if (data.url != null) {
                form.action = data.url;
                form.method = "POST";
                form.target = '3ds';
                for (var prop in elements) {
                    if (!elements.hasOwnProperty(prop)) {
                        continue;
                    }
                    var element = document.createElement("input");
                    element.name = prop;
                    element.value = elements[prop];
                    element.type = "hidden";
                    form.appendChild(element);
                }
                document.body.appendChild(form);
                createActionButton("btnDiv", psAcsData.cancelText, actionUrl, '');
                form.submit();
            } else {
                showError();
            }
        }

        /**
         * Shows error message and continue button
         */
        function showError()
        {
            var ifr = document.getElementById("3ds");
            ifr.src = "";
            ifr.style.height = "0";
            var actionUrl = psAcsData.errorActionUrl;
            createMessage("contentDiv", psAcsData.errorText);
            createActionButton("btnDiv", psAcsData.continueText, actionUrl, "action primary continue");
        }

        /**
         * Creates an iframe where the 3-D Secure authentication form will be loaded
         */
        function createRedirectFrame(divId)
        {
            var targetDiv = document.getElementById(divId);
            var ifr = document.createElement("iframe");
            ifr.src = psAcsData.loader;
            ifr.id = "3ds";
            ifr.name = ifr.id;
            ifr.style.width = "100%";
            ifr.style.height = "400px";
            ifr.style.overflowY = "scroll";
            ifr.style.border = "0";
            targetDiv.appendChild(ifr);
            return ifr.name;
        }

        /**
         * Creates action buttons
         *
         * Used for cancel and continue buttons
         */
        function createActionButton(divId, msg, href, className)
        {
            var targetDiv = document.getElementById(divId);
            var btn = document.createElement("a");
            var linkText = document.createTextNode(msg);
            btn.appendChild(linkText);
            btn.title = msg;
            btn.href = href;
            btn.className = className;
            targetDiv.appendChild(btn);
        }

        /**
         * Creates messages
         *
         * Used for showing error messages occurring while retrieving data from the data provider
         */
        function createMessage(divId, msg)
        {
            var targetDiv = document.getElementById(divId);
            var content = document.createTextNode(msg);
            targetDiv.style.marginBottom = "15px";
            targetDiv.appendChild(content);
        }
    }
);
