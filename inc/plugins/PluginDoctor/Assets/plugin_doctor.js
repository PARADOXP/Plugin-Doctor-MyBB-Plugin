/*
 * Plugin Doctor - Admin CP dashboard helper.
 *
 * Two small controllers live here:
 *   1. A confirmation prompt for every form that carries data-confirm.
 *   2. The chunked scan controller that asks permission before scanning and
 *      polls the server for live per file progress.
 *
 * All user facing text is rendered by the templates or returned by the
 * language files through the JSON endpoint. This file never hardcodes it.
 */
(function ()
{
	"use strict";

	function bindConfirm(form)
	{
		if(!form || form.getAttribute("data-plugin-doctor-bound") === "1")
		{
			return;
		}

		form.setAttribute("data-plugin-doctor-bound", "1");

		form.addEventListener("submit", function (event)
		{
			var message = form.getAttribute("data-confirm");

			if(message && !window.confirm(message))
			{
				event.preventDefault();
				event.stopPropagation();

				return false;
			}

			// Guard against a double submit while the request is in flight.
			var buttons = form.querySelectorAll("button, input[type=submit]");

			for(var i = 0; i < buttons.length; i++)
			{
				buttons[i].disabled = true;
			}
		});
	}

	function bindConfirms()
	{
		var forms = document.querySelectorAll("form[data-confirm]");

		for(var i = 0; i < forms.length; i++)
		{
			bindConfirm(forms[i]);
		}
	}

	function bindModals()
	{
		var openers = document.querySelectorAll("[data-plugin-doctor-modal-open]");
		var modals = document.querySelectorAll("[data-plugin-doctor-modal]");

		if(!openers.length || !modals.length)
		{
			return;
		}

		var openModal = null;
		var lastFocus = null;

		function closeModal(modal)
		{
			if(!modal)
			{
				return;
			}

			modal.hidden = true;
			modal.removeAttribute("data-open");
			openModal = null;

			if(lastFocus && typeof lastFocus.focus === "function")
			{
				lastFocus.focus();
			}

			lastFocus = null;
		}

		function showModal(modal)
		{
			if(!modal)
			{
				return;
			}

			lastFocus = document.activeElement;
			openModal = modal;
			modal.hidden = false;
			modal.setAttribute("data-open", "1");

			var closeButton = modal.querySelector("[data-modal-close]");

			if(closeButton && typeof closeButton.focus === "function")
			{
				closeButton.focus();
			}
		}

		for(var i = 0; i < openers.length; i++)
		{
			openers[i].addEventListener("click", function (event)
			{
				event.preventDefault();

				var target = this.getAttribute("data-plugin-doctor-modal-open");

				if(target)
				{
					showModal(document.getElementById(target));
				}
			});
		}

		for(var j = 0; j < modals.length; j++)
		{
			var closers = modals[j].querySelectorAll("[data-modal-close]");

			for(var k = 0; k < closers.length; k++)
			{
				closers[k].addEventListener("click", function (event)
				{
					event.preventDefault();

					var node = this;

					while(node && node.nodeType === 1 && !node.hasAttribute("data-plugin-doctor-modal"))
					{
						node = node.parentNode;
					}

					if(node && node.nodeType === 1 && node.hasAttribute("data-plugin-doctor-modal"))
					{
						closeModal(node);
					}
				});
			}
		}

		document.addEventListener("keydown", function (event)
		{
			if(openModal && (event.key === "Escape" || event.keyCode === 27))
			{
				event.preventDefault();
				closeModal(openModal);
			}
		});
	}

	function bindScan()
	{		var root = document.querySelector("[data-plugin-doctor-scan]");

		if(!root)
		{
			return;
		}

		var promptPanel = root.querySelector("[data-scan-prompt]");
		var manualPanel = root.querySelector("[data-scan-manual]");
		var progressPanel = root.querySelector("[data-scan-progress]");
		var bar = root.querySelector("[data-scan-progress-bar]");
		var statusEl = root.querySelector("[data-scan-status]");
		var pluginsEl = root.querySelector("[data-scan-plugins]");
		var filesEl = root.querySelector("[data-scan-files]");
		var currentEl = root.querySelector("[data-scan-current]");
		var logEl = root.querySelector("[data-scan-log]");
		var autoButton = root.querySelector("[data-scan-auto]");
		var manualToggle = root.querySelector("[data-scan-manual-toggle]");
		var manualStart = root.querySelector("[data-scan-manual-start]");
		var manualCancel = root.querySelector("[data-scan-manual-cancel]");
		var cancelButton = root.querySelector("[data-scan-cancel]");

		var startUrl = root.getAttribute("data-scan-start");
		var stepUrl = root.getAttribute("data-scan-step");
		var cancelUrl = root.getAttribute("data-scan-cancel");
		var postKey = root.getAttribute("data-post-key");
		var errorText = root.getAttribute("data-scan-error") || "";
		var token = root.getAttribute("data-scan-token") || "";
		var running = false;

		if(!promptPanel || !progressPanel || !bar)
		{
			return;
		}

		function setBar(percent)
		{
			percent = parseFloat(percent);

			if(isNaN(percent))
			{
				percent = 0;
			}

			percent = Math.max(0, Math.min(100, percent));
			bar.style.width = percent.toFixed(1) + "%";
		}

		function setBusy(busy)
		{
			if(autoButton)
			{
				autoButton.disabled = busy;
			}

			if(manualToggle)
			{
				manualToggle.disabled = busy;
			}

			if(manualStart)
			{
				manualStart.disabled = busy;
			}
		}

		function showProgress()
		{
			promptPanel.hidden = true;
			progressPanel.hidden = false;
		}

		function showPrompt()
		{
			progressPanel.hidden = true;
			promptPanel.hidden = false;
		}

		function resetPanel()
		{
			running = false;
			token = "";
			setBusy(false);
			setBar(0);
			showPrompt();
		}

		function renderLog(entries)
		{
			if(!logEl || !entries || typeof entries.length === "undefined")
			{
				return;
			}

			while(logEl.firstChild)
			{
				logEl.removeChild(logEl.firstChild);
			}

			for(var i = 0; i < entries.length; i++)
			{
				var item = document.createElement("li");
				var code = document.createElement("code");
				var state = entries[i].status || "skipped";

				if(["scanned", "skipped", "missing", "error"].indexOf(state) === -1)
				{
					state = "skipped";
				}

				item.className = "plugin_doctor_log_" + state;
				code.textContent = entries[i].file || "";
				item.appendChild(code);
				logEl.appendChild(item);
			}
		}

		function update(data)
		{
			setBar(data.percent);

			if(statusEl && data.message)
			{
				statusEl.textContent = data.message;
			}

			if(pluginsEl && typeof data.plugins_done !== "undefined")
			{
				pluginsEl.textContent = data.plugins_done + " / " + data.plugins_total;
			}

			if(filesEl && typeof data.files_done !== "undefined")
			{
				filesEl.textContent = data.files_done + " / " + data.files_total;
			}

			if(currentEl)
			{
				currentEl.textContent = data.current || "";
			}

			renderLog(data.log);
		}

		function post(url, fields)
		{
			var body = new FormData();
			body.append("my_post_key", postKey);
			body.append("ajax", "1");

			for(var key in fields)
			{
				if(Object.prototype.hasOwnProperty.call(fields, key))
				{
					body.append(key, fields[key]);
				}
			}

			return fetch(url, {
				method: "POST",
				body: body,
				credentials: "same-origin",
				headers: {"X-Requested-With": "XMLHttpRequest"}
			}).then(function (response)
			{
				return response.text();
			}).then(function (text)
			{
				return JSON.parse(text);
			});
		}

		function showFailure(data)
		{
			running = false;
			setBusy(false);

			if(statusEl)
			{
				statusEl.textContent = (data && data.message) ? data.message : errorText;
			}

			window.setTimeout(function ()
			{
				token = "";
				showPrompt();
			}, 2500);
		}

		function finish(data)
		{
			running = false;
			setBusy(false);
			update(data);

			if(data.redirect)
			{
				window.setTimeout(function ()
				{
					window.location.href = data.redirect;
				}, 900);
			}
		}

		function scheduleStep(delay)
		{
			window.setTimeout(poll, delay);
		}

		function poll()
		{
			if(!running || token === "")
			{
				return;
			}

			post(stepUrl, {token: token}).then(function (data)
			{
				if(data.status === "busy")
				{
					scheduleStep(450);

					return;
				}

				if(data.status === "error")
				{
					showFailure(data);

					return;
				}

				update(data);

				if(data.status === "done")
				{
					finish(data);

					return;
				}

				if(data.status === "cancelled")
				{
					resetPanel();

					return;
				}

				scheduleStep(25);
			}).catch(function ()
			{
				showFailure(null);
			});
		}

		function start(fields)
		{
			if(running)
			{
				return;
			}

			running = true;
			setBusy(true);
			setBar(0);
			showProgress();

			post(startUrl, fields).then(function (data)
			{
				if(data.status === "error")
				{
					showFailure(data);

					return;
				}

				if(data.token)
				{
					token = data.token;
				}

				update(data);

				if(data.status === "done")
				{
					finish(data);

					return;
				}

				scheduleStep(0);
			}).catch(function ()
			{
				showFailure(null);
			});
		}

		if(autoButton)
		{
			autoButton.addEventListener("click", function ()
			{
				start({mode: "auto"});
			});
		}

		if(manualToggle && manualPanel)
		{
			manualToggle.addEventListener("click", function ()
			{
				manualPanel.hidden = !manualPanel.hidden;
			});
		}

		if(manualCancel && manualPanel)
		{
			manualCancel.addEventListener("click", function ()
			{
				manualPanel.hidden = true;
			});
		}

		if(manualStart && manualPanel)
		{
			manualStart.addEventListener("click", function ()
			{
				var inputs = manualPanel.querySelectorAll("[data-scan-field]");
				var fields = {mode: "manual"};

				for(var i = 0; i < inputs.length; i++)
				{
					fields[inputs[i].getAttribute("data-scan-field")] = inputs[i].value;
				}

				start(fields);
			});
		}

		if(cancelButton)
		{
			cancelButton.addEventListener("click", function ()
			{
				if(token === "")
				{
					resetPanel();

					return;
				}

				post(cancelUrl, {token: token}).then(function ()
				{
					resetPanel();
				}).catch(function ()
				{
					resetPanel();
				});
			});
		}

		setBar(bar.getAttribute("data-percent"));

		if(root.getAttribute("data-scan-active") === "1" && token !== "")
		{
			running = true;
			setBusy(true);
			showProgress();
			scheduleStep(0);
		}
		else
		{
			showPrompt();
		}
	}

	function init()
	{
		bindConfirms();
		bindModals();
		bindScan();
	}

	if(document.readyState === "loading")
	{
		document.addEventListener("DOMContentLoaded", init);
	}
	else
	{
		init();
	}
})();
