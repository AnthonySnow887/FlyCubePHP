/*
fly-cube-php-ujs based on rails-ujs.
https://github.com/rails/rails/blob/master/actionview/app/assets/javascripts
Released under the MIT license
*/

(function() {
    var context = this;

    (function() {
        (function() {
            this.FlyCupePHP = {
                linkClickSelector: 'a[data-confirm], a[data-method], a[data-remote]:not([disabled]), a[data-disable-with], a[data-disable]',
                buttonClickSelector: {
                    selector: 'button[data-remote]:not([form]), button[data-confirm]:not([form])',
                    exclude: 'form button'
                },
                inputChangeSelector: 'select[data-remote], input[data-remote], textarea[data-remote]',
                formSubmitSelector: 'form',
                formInputClickSelector: 'form input[type=submit], form input[type=image], form button[type=submit], form button:not([type]), input[type=submit][form], input[type=image][form], button[type=submit][form], button[form]:not([type])',
                formDisableSelector: 'input[data-disable-with]:enabled, button[data-disable-with]:enabled, textarea[data-disable-with]:enabled, input[data-disable]:enabled, button[data-disable]:enabled, textarea[data-disable]:enabled',
                formEnableSelector: 'input[data-disable-with]:disabled, button[data-disable-with]:disabled, textarea[data-disable-with]:disabled, input[data-disable]:disabled, button[data-disable]:disabled, textarea[data-disable]:disabled',
                fileInputSelector: 'input[name][type=file]:not([disabled])',
                linkDisableSelector: 'a[data-disable-with], a[data-disable]',
                buttonDisableSelector: 'button[data-remote][data-disable-with], button[data-remote][data-disable]'
            };
        }).call(this);
    }).call(context);

    var FlyCupePHP = context.FlyCupePHP;

    (function() {
        (function() {
            var nonce = null;

            FlyCupePHP.loadCSPNonce = function() {
                let ref;
                return nonce = (ref = document.querySelector("meta[name=csp-nonce]")) != null ? ref.content : void 0;
            };

            FlyCupePHP.cspNonce = function() {
                return nonce != null ? nonce : FlyCupePHP.loadCSPNonce();
            };

        }).call(this);

        (function() {
            var m = Element.prototype.matches
                  || Element.prototype.matchesSelector
                  || Element.prototype.mozMatchesSelector
                  || Element.prototype.msMatchesSelector
                  || Element.prototype.oMatchesSelector
                  || Element.prototype.webkitMatchesSelector;

            FlyCupePHP.matches = function(element, selector) {
                if (selector.exclude != null) {
                    return m.call(element, selector.selector) && !m.call(element, selector.exclude);
                } else {
                    return m.call(element, selector);
                }
            };

            var expando = '_ujsData';
            FlyCupePHP.getData = function(element, key) {
                let ref;
                return (ref = element[expando]) != null ? ref[key] : void 0;
            };

            FlyCupePHP.setData = function(element, key, value) {
                if (element[expando] == null) {
                    element[expando] = {};
                }
                return element[expando][key] = value;
            };

            FlyCupePHP.$ = function(selector) {
                return Array.prototype.slice.call(document.querySelectorAll(selector));
            };
        }).call(this);

        (function() {
            var $ = FlyCupePHP.$;
            var csrfToken = FlyCupePHP.csrfToken = function() {
                let meta = document.querySelector('meta[name=csrf-token]');
                return meta && meta.content;
            };
            var csrfParam = FlyCupePHP.csrfParam = function() {
                let meta = document.querySelector('meta[name=csrf-param]');
                return meta && meta.content;
            };

            FlyCupePHP.CSRFProtection = function(xhr) {
                let token = csrfToken();
                // console.log("TOKEN:", token);
                if (token != null) {
                    return xhr.setRequestHeader('X-CSRF-Token', token);
                }
            };

            FlyCupePHP.refreshCSRFTokens = function() {
                let token = csrfToken();
                let param = csrfParam();
                if ((token != null) && (param != null)) {
                    return $('form input[name="' + param + '"]').forEach(function(input) {
                        return input.value = token;
                  });
                }
            };

        }).call(this);

        (function() {
            var matches = FlyCupePHP.matches;
            var CustomEvent = window.CustomEvent;
            if (typeof CustomEvent !== 'function') {
                CustomEvent = function(event, params) {
                    let evt = document.createEvent('CustomEvent');
                    evt.initCustomEvent(event, params.bubbles, params.cancelable, params.detail);
                    return evt;
            };
            CustomEvent.prototype = window.Event.prototype;
            var preventDefault = CustomEvent.prototype.preventDefault;
            CustomEvent.prototype.preventDefault = function() {
                let result = preventDefault.call(this);
                if (this.cancelable && !this.defaultPrevented) {
                    Object.defineProperty(this, 'defaultPrevented', {
                        get: function() {
                            return true;
                        }
                    });
                }
                return result;
            };
            }
            var fire = FlyCupePHP.fire = function(obj, name, data) {
                let event = new CustomEvent(name, {
                    bubbles: true,
                    cancelable: true,
                    detail: data
                });
                obj.dispatchEvent(event);
                return !event.defaultPrevented;
            };

            FlyCupePHP.stopEverything = function(e) {
                fire(e.target, 'ujs:everythingStopped');
                e.preventDefault();
                e.stopPropagation();
                return e.stopImmediatePropagation();
            };

            FlyCupePHP.delegate = function(element, selector, eventType, handler) {
                return element.addEventListener(eventType, function(e) {
                    let target = e.target;
                    while (!(!(target instanceof Element) || matches(target, selector))) {
                        target = target.parentNode;
                    }
                    if (target instanceof Element && handler.call(target, e) === false) {
                        e.preventDefault();
                        return e.stopPropagation();
                    }
                });
            };
        }).call(this);

        (function() {
            var cspNonce = FlyCupePHP.cspNonce;
            var CSRFProtection = FlyCupePHP.CSRFProtection;
            var fire = FlyCupePHP.fire;

            var AcceptHeaders = {
                '*': '*/*',
                text: 'text/plain',
                html: 'text/html',
                xml: 'application/xml, text/xml',
                json: 'application/json, text/json',
                script: 'text/javascript, application/javascript, application/ecmascript, application/x-ecmascript'
            };

            FlyCupePHP.ajax = function(options) {
                options = prepareOptions(options);
                let xhr = createXHR(options, function() {
                    let ref, response;
                    response = processResponse((ref = xhr.response) != null ? ref : xhr.responseText, xhr.getResponseHeader('Content-Type'));
                    if (Math.floor(xhr.status / 100) === 2) {
                        if (typeof options.success === "function") {
                            options.success(response, xhr.statusText, xhr);
                        }
                    } else {
                        if (typeof options.error === "function") {
                            options.error(response, xhr.statusText, xhr);
                        }
                    }
                    return typeof options.complete === "function" ? options.complete(xhr, xhr.statusText) : void 0;
                });
                if ((options.beforeSend != null) && !options.beforeSend(xhr, options)) {
                    return false;
                }
                if (xhr.readyState === XMLHttpRequest.OPENED) {
                    return xhr.send(options.data);
                }
            };

            var prepareOptions = function(options) {
                options.url = options.url || location.href;
                options.type = options.type.toUpperCase();
                if (options.type === 'GET' && options.data) {
                    if (options.url.indexOf('?') < 0) {
                        options.url += '?' + options.data;
                    } else {
                        options.url += '&' + options.data;
                    }
                }
                if (AcceptHeaders[options.dataType] == null) {
                    options.dataType = '*';
                }
                options.accept = AcceptHeaders[options.dataType];
                if (options.dataType !== '*') {
                    options.accept += ', */*; q=0.01';
                }
                return options;
            };

            var createXHR = function(options, done) {
                var xhr;
                xhr = new XMLHttpRequest();
                xhr.open(options.type, options.url, true);
                xhr.setRequestHeader('Accept', options.accept);
                if (typeof options.data === 'string') {
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
                }
                // console.log(options.crossDomain);
                if (!options.crossDomain) {
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    CSRFProtection(xhr);
                }
                xhr.withCredentials = !!options.withCredentials;
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === XMLHttpRequest.DONE) {
                        return done(xhr);
                    }
                };
                return xhr;
            };

            var processResponse = function(response, type) {
                let parser, script;
                if (typeof response === 'string' && typeof type === 'string') {
                    if (type.match(/\bjson\b/)) {
                        try {
                            response = JSON.parse(response);
                        } catch (error) {
                        }
                    } else if (type.match(/\b(?:java|ecma)script\b/)) {
                        script = document.createElement('script');
                        script.setAttribute('nonce', cspNonce());
                        script.text = response;
                        document.head.appendChild(script).parentNode.removeChild(script);
                    } else if (type.match(/\b(xml|html|svg)\b/)) {
                        parser = new DOMParser();
                        type = type.replace(/;.+/, '');
                        try {
                            response = parser.parseFromString(response, type);
                        } catch (error) {
                        }
                    }
                }
                return response;
            };

            FlyCupePHP.href = function(element) {
                return element.href;
            };

            FlyCupePHP.isCrossDomain = function(url) {
                let e, originAnchor, urlAnchor;
                originAnchor = document.createElement('a');
                originAnchor.href = location.href;
                urlAnchor = document.createElement('a');
                try {
                    urlAnchor.href = url;
                    return !(((!urlAnchor.protocol || urlAnchor.protocol === ':') && !urlAnchor.host)
                            || (originAnchor.protocol + '//' + originAnchor.host === urlAnchor.protocol + '//' + urlAnchor.host));
                } catch (error) {
                    e = error;
                    return true;
                }
            };
        }).call(this);

        (function() {
            var matches = FlyCupePHP.matches;
            var toArray = function(e) {
                return Array.prototype.slice.call(e);
            };

            FlyCupePHP.serializeElement = function(element, additionalParam) {
                let inputs = [element];
                if (matches(element, 'form')) {
                    inputs = toArray(element.elements);
                }
                let params = [];
                inputs.forEach(function(input) {
                    if (!input.name || input.disabled) {
                        return;
                    }
                    if (matches(input, 'select')) {
                        return toArray(input.options).forEach(function(option) {
                            if (option.selected) {
                                return params.push({
                                    name: input.name,
                                    value: option.value
                                });
                            }
                        });
                    } else if (input.checked || ['radio', 'checkbox', 'submit'].indexOf(input.type) === -1) {
                        return params.push({
                            name: input.name,
                            value: input.value
                        });
                    }
                });
                if (additionalParam) {
                    params.push(additionalParam);
                }
                return params.map(function(param) {
                    if (param.name != null) {
                        return (encodeURIComponent(param.name)) + "=" + (encodeURIComponent(param.value));
                    } else {
                        return param;
                    }
                }).join('&');
            };

            FlyCupePHP.formElements = function(form, selector) {
                if (matches(form, 'form')) {
                    return toArray(form.elements).filter(function(el) {
                    return matches(el, selector);
                  });
                } else {
                    return toArray(form.querySelectorAll(selector));
                }
            };
        }).call(this);

        (function() {
            var fire = FlyCupePHP.fire;
            var stopEverything = FlyCupePHP.stopEverything;

            FlyCupePHP.handleConfirm = function(e) {
                // console.log("--3--");
                if (!allowAction(this)) {
                    return stopEverything(e);
                }
            };

            var allowAction = function(element) {
                // console.log("--4--");
                let callback;
                let message = element.getAttribute('data-confirm');
                if (!message) {
                    return true;
                }
                let answer = false;
                if (fire(element, 'confirm')) {
                    try {
                        answer = confirm(message);
                    } catch (error) {}
                    callback = fire(element, 'confirm:complete', [answer]);
                }
                return answer && callback;
            };
        }).call(this);

        (function() {
            var matches = FlyCupePHP.matches;
            var getData = FlyCupePHP.getData;
            var setData = FlyCupePHP.setData;
            var stopEverything = FlyCupePHP.stopEverything;
            var formElements = FlyCupePHP.formElements;

            FlyCupePHP.handleDisabledElement = function(e) {
                // console.log("--2--");
                let element = this;
                if (element.disabled) {
                    return stopEverything(e);
                }
            };

            FlyCupePHP.enableElement = function(e) {
                let element = e instanceof Event ? e.target : e;
                if (matches(element, FlyCupePHP.linkDisableSelector)) {
                    return enableLinkElement(element);
                } else if (matches(element, FlyCupePHP.buttonDisableSelector) || matches(element, FlyCupePHP.formEnableSelector)) {
                    return enableFormElement(element);
                } else if (matches(element, FlyCupePHP.formSubmitSelector)) {
                    return enableFormElements(element);
                }
            };

            FlyCupePHP.disableElement = function(e) {
                // console.log("--5--");
                let element = e instanceof Event ? e.target : e;
                if (matches(element, FlyCupePHP.linkDisableSelector)) {
                    return disableLinkElement(element);
                } else if (matches(element, FlyCupePHP.buttonDisableSelector) || matches(element, FlyCupePHP.formDisableSelector)) {
                    return disableFormElement(element);
                } else if (matches(element, FlyCupePHP.formSubmitSelector)) {
                    return disableFormElements(element);
                }
            };

            var disableLinkElement = function(element) {
                // console.log("--6--");
                let replacement = element.getAttribute('data-disable-with');
                if (replacement != null) {
                    setData(element, 'ujs:enable-with', element.innerHTML);
                    element.innerHTML = replacement;
                }
                element.addEventListener('click', stopEverything);
                return setData(element, 'ujs:disabled', true);
            };

            var enableLinkElement = function(element) {
                let originalText = getData(element, 'ujs:enable-with');
                if (originalText != null) {
                    element.innerHTML = originalText;
                    setData(element, 'ujs:enable-with', null);
                }
                element.removeEventListener('click', stopEverything);
                return setData(element, 'ujs:disabled', null);
            };

            var disableFormElements = function(form) {
                return formElements(form, FlyCupePHP.formDisableSelector).forEach(disableFormElement);
            };

            var disableFormElement = function(element) {
                // console.log("--7--");
                let replacement = element.getAttribute('data-disable-with');
                if (replacement != null) {
                    if (matches(element, 'button')) {
                        setData(element, 'ujs:enable-with', element.innerHTML);
                        element.innerHTML = replacement;
                    } else {
                        setData(element, 'ujs:enable-with', element.value);
                        element.value = replacement;
                    }
                }
                element.disabled = true;
                return setData(element, 'ujs:disabled', true);
            };

            var enableFormElements = function(form) {
                return formElements(form, FlyCupePHP.formEnableSelector).forEach(enableFormElement);
            };

            var enableFormElement = function(element) {
                let originalText = getData(element, 'ujs:enable-with');
                if (originalText != null) {
                    if (matches(element, 'button')) {
                        element.innerHTML = originalText;
                    } else {
                        element.value = originalText;
                    }
                    setData(element, 'ujs:enable-with', null);
                }
                element.disabled = false;
                return setData(element, 'ujs:disabled', null);
            };
        }).call(this);

        (function() {
            var stopEverything = FlyCupePHP.stopEverything;

            FlyCupePHP.handleMethod = function(e) {
                // console.log("--10--");
                let link = this;
                let method = link.getAttribute('data-method');
                if (!method) {
                    return;
                }
                let href = FlyCupePHP.href(link);
                let csrfToken = FlyCupePHP.csrfToken();
                let csrfParam = FlyCupePHP.csrfParam();
                let form = document.createElement('form');
                let formContent = "<input name='_method' value='" + method + "' type='hidden' />";
                if ((csrfParam != null) && (csrfToken != null) && !FlyCupePHP.isCrossDomain(href)) {
                    formContent += "<input name='" + csrfParam + "' value='" + csrfToken + "' type='hidden' />";
                }
                formContent += '<input type="submit" />';
                form.method = 'post';
                form.action = href;
                form.target = link.target;
                form.innerHTML = formContent;
                form.style.display = 'none';
                document.body.appendChild(form);
                form.querySelector('[type="submit"]').click();
                return stopEverything(e);
            };
        }).call(this);

        (function() {
            var slice = [].slice;
            var matches = FlyCupePHP.matches;
            var getData = FlyCupePHP.getData;
            var setData = FlyCupePHP.setData;
            var fire = FlyCupePHP.fire;
            var stopEverything = FlyCupePHP.stopEverything;
            var ajax = FlyCupePHP.ajax;
            var isCrossDomain = FlyCupePHP.isCrossDomain;
            var serializeElement = FlyCupePHP.serializeElement;

            var isRemote = function(element) {
                // console.log("--9--");
                let value = element.getAttribute('data-remote');
                return (value != null) && value !== 'false';
            };

            FlyCupePHP.handleRemote = function(e) {
                // console.log("--8--");
                let element = this;
                if (!isRemote(element)) {
                    return true;
                }
                if (!fire(element, 'ajax:before')) {
                    fire(element, 'ajax:stopped');
                    return false;
                }
                let withCredentials = element.getAttribute('data-with-credentials');
                let dataType = element.getAttribute('data-type') || 'script';
                let method, url, data;
                if (matches(element, FlyCupePHP.formSubmitSelector)) {
                    let button = getData(element, 'ujs:submit-button');
                    method = getData(element, 'ujs:submit-button-formmethod') || element.method;
                    url = getData(element, 'ujs:submit-button-formaction') || element.getAttribute('action') || location.href;
                    if (method.toUpperCase() === 'GET') {
                        url = url.replace(/\?.*$/, '');
                    }
                    if (element.enctype === 'multipart/form-data') {
                        data = new FormData(element);
                        if (button != null) {
                        data.append(button.name, button.value);
                        }
                    } else {
                        data = serializeElement(element, button);
                    }
                    setData(element, 'ujs:submit-button', null);
                    setData(element, 'ujs:submit-button-formmethod', null);
                    setData(element, 'ujs:submit-button-formaction', null);
                } else if (matches(element, FlyCupePHP.buttonClickSelector) || matches(element, FlyCupePHP.inputChangeSelector)) {
                    method = element.getAttribute('data-method');
                    url = element.getAttribute('data-url');
                    data = serializeElement(element, element.getAttribute('data-params'));
                } else {
                    method = element.getAttribute('data-method');
                    url = FlyCupePHP.href(element);
                    data = element.getAttribute('data-params');
                }
                ajax({
                    type: method || 'GET',
                    url: url,
                    data: data,
                    dataType: dataType,
                    beforeSend: function(xhr, options) {
                        if (fire(element, 'ajax:beforeSend', [xhr, options])) {
                            return fire(element, 'ajax:send', [xhr]);
                        } else {
                            fire(element, 'ajax:stopped');
                            return false;
                        }
                    },
                    success: function() {
                        var args;
                        args = 1 <= arguments.length ? slice.call(arguments, 0) : [];
                        return fire(element, 'ajax:success', args);
                    },
                    error: function() {
                        var args;
                        args = 1 <= arguments.length ? slice.call(arguments, 0) : [];
                        return fire(element, 'ajax:error', args);
                    },
                    complete: function() {
                        var args;
                        args = 1 <= arguments.length ? slice.call(arguments, 0) : [];
                        return fire(element, 'ajax:complete', args);
                    },
                    crossDomain: isCrossDomain(url),
                    withCredentials: (withCredentials != null) && withCredentials !== 'false'
                });
                return stopEverything(e);
            };

            FlyCupePHP.formSubmitButtonClick = function(e) {
                let button = this;
                let form = button.form;
                if (!form) {
                    return;
                }
                if (button.name) {
                    setData(form, 'ujs:submit-button', {
                        name: button.name,
                        value: button.value
                    });
                }
                setData(form, 'ujs:formnovalidate-button', button.formNoValidate);
                setData(form, 'ujs:submit-button-formaction', button.getAttribute('formaction'));
                return setData(form, 'ujs:submit-button-formmethod', button.getAttribute('formmethod'));
            };

            FlyCupePHP.preventInsignificantClick = function(e) {
                // console.log("--1--");
                let link = this;
                let method = (link.getAttribute('data-method') || 'GET').toUpperCase();
                let data = link.getAttribute('data-params');
                let metaClick = e.metaKey || e.ctrlKey;
                let insignificantMetaClick = metaClick && method === 'GET' && !data;
                let nonPrimaryMouseClick = (e.button != null) && e.button !== 0;
                if (nonPrimaryMouseClick || insignificantMetaClick) {
                    return e.stopImmediatePropagation();
                }
            };
        }).call(this);

        (function() {
            var fire = FlyCupePHP.fire;
            var delegate = FlyCupePHP.delegate;
            var getData = FlyCupePHP.getData;
            var $ = FlyCupePHP.$;
            var refreshCSRFTokens = FlyCupePHP.refreshCSRFTokens;
            var CSRFProtection = FlyCupePHP.CSRFProtection;
            var loadCSPNonce = FlyCupePHP.loadCSPNonce;
            var enableElement = FlyCupePHP.enableElement;
            var disableElement = FlyCupePHP.disableElement;
            var handleDisabledElement = FlyCupePHP.handleDisabledElement;
            var handleConfirm = FlyCupePHP.handleConfirm;
            var preventInsignificantClick = FlyCupePHP.preventInsignificantClick;
            var handleRemote = FlyCupePHP.handleRemote;
            var formSubmitButtonClick = FlyCupePHP.formSubmitButtonClick;
            var handleMethod = FlyCupePHP.handleMethod;

            if ((typeof jQuery !== "undefined" && jQuery !== null) && (jQuery.ajax != null)) {
                if (jQuery.flyCupePHP) {
                    throw new Error('If you load both jquery_ujs and fly-cube-php-ujs, use fly-cube-php-ujs only.');
                }
                jQuery.flyCupePHP = FlyCupePHP;
                jQuery.ajaxPrefilter(function(options, originalOptions, xhr) {
                    // console.log(options.crossDomain);
                    if (!options.crossDomain) {
                        return CSRFProtection(xhr);
                    }
                });
            }

            FlyCupePHP.start = function() {
                if (window._fly_cupe_php_loaded) {
                    throw new Error('fly-cube-php has already been loaded!');
                }
                window.addEventListener('pageshow', function() {
                    $(FlyCupePHP.formEnableSelector).forEach(function(el) {
                        if (getData(el, 'ujs:disabled')) {
                            return enableElement(el);
                        }
                    });
                    return $(FlyCupePHP.linkDisableSelector).forEach(function(el) {
                        if (getData(el, 'ujs:disabled')) {
                            return enableElement(el);
                        }
                    });
                });
                delegate(document, FlyCupePHP.linkDisableSelector, 'ajax:complete', enableElement);
                delegate(document, FlyCupePHP.linkDisableSelector, 'ajax:stopped', enableElement);
                delegate(document, FlyCupePHP.buttonDisableSelector, 'ajax:complete', enableElement);
                delegate(document, FlyCupePHP.buttonDisableSelector, 'ajax:stopped', enableElement);
                delegate(document, FlyCupePHP.linkClickSelector, 'click', preventInsignificantClick);
                delegate(document, FlyCupePHP.linkClickSelector, 'click', handleDisabledElement);
                delegate(document, FlyCupePHP.linkClickSelector, 'click', handleConfirm);
                delegate(document, FlyCupePHP.linkClickSelector, 'click', disableElement);
                delegate(document, FlyCupePHP.linkClickSelector, 'click', handleRemote);
                delegate(document, FlyCupePHP.linkClickSelector, 'click', handleMethod);
                delegate(document, FlyCupePHP.buttonClickSelector, 'click', preventInsignificantClick);
                delegate(document, FlyCupePHP.buttonClickSelector, 'click', handleDisabledElement);
                delegate(document, FlyCupePHP.buttonClickSelector, 'click', handleConfirm);
                delegate(document, FlyCupePHP.buttonClickSelector, 'click', disableElement);
                delegate(document, FlyCupePHP.buttonClickSelector, 'click', handleRemote);
                delegate(document, FlyCupePHP.inputChangeSelector, 'change', handleDisabledElement);
                delegate(document, FlyCupePHP.inputChangeSelector, 'change', handleConfirm);
                delegate(document, FlyCupePHP.inputChangeSelector, 'change', handleRemote);
                delegate(document, FlyCupePHP.formSubmitSelector, 'submit', handleDisabledElement);
                delegate(document, FlyCupePHP.formSubmitSelector, 'submit', handleConfirm);
                delegate(document, FlyCupePHP.formSubmitSelector, 'submit', handleRemote);
                delegate(document, FlyCupePHP.formSubmitSelector, 'submit', function(e) {
                    return setTimeout((function() {
                        return disableElement(e);
                    }), 13);
                });
                delegate(document, FlyCupePHP.formSubmitSelector, 'ajax:send', disableElement);
                delegate(document, FlyCupePHP.formSubmitSelector, 'ajax:complete', enableElement);
                delegate(document, FlyCupePHP.formInputClickSelector, 'click', preventInsignificantClick);
                delegate(document, FlyCupePHP.formInputClickSelector, 'click', handleDisabledElement);
                delegate(document, FlyCupePHP.formInputClickSelector, 'click', handleConfirm);
                delegate(document, FlyCupePHP.formInputClickSelector, 'click', formSubmitButtonClick);
                document.addEventListener('DOMContentLoaded', refreshCSRFTokens);
                document.addEventListener('DOMContentLoaded', loadCSPNonce);
                return window._fly_cupe_php_loaded = true;
            };

            if (window.FlyCupePHP === FlyCupePHP && fire(document, 'flyCubePHP:attachBindings')) {
                FlyCupePHP.start();
            }
        }).call(this);
    }).call(this);

    if (typeof module === "object" && module.exports) {
        module.exports = FlyCupePHP;
    } else if (typeof define === "function" && define.amd) {
        define(FlyCupePHP);
    }
}).call(this);
 
