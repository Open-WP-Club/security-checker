console.log("wordpress-checker.js loaded");

document.addEventListener("DOMContentLoaded", function () {
  console.log("DOM fully loaded and parsed");
  const form = document.getElementById("urlForm");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      console.log("Form submitted");
      const url = document.getElementById("urlInput").value;
      checkWordPressSite(url);
    });
  } else {
    console.error("Form element not found");
  }
});

async function checkWordPressSite(url) {
  console.log("Checking WordPress site:", url);
  const loadingDiv = document.getElementById("loading");
  const resultsTable = document.getElementById("resultsTable");

  if (!loadingDiv || !resultsTable) {
    console.error("One or more required elements not found");
    return;
  }

  loadingDiv.classList.remove("hidden");
  setTableToLoading();

  try {
    const response = await fetch("wp_checker.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `url=${encodeURIComponent(url)}`,
    });

    const reader = response.body.getReader();
    const decoder = new TextDecoder();

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      const chunk = decoder.decode(value, { stream: true });
      const lines = chunk.split("\n").filter((line) => line.trim() !== "");

      for (const line of lines) {
        try {
          const data = JSON.parse(line);
          updateTableContent(data);
        } catch (error) {
          console.error("Error parsing JSON:", error);
        }
      }
    }
  } catch (error) {
    console.error("Error:", error);
    updateField("wpIssues", `Error: ${error.message}`, "text-red-500");
  } finally {
    loadingDiv.classList.add("hidden");
  }
}

function setTableToLoading() {
  const fields = [
    "isWordPress",
    "wpVersion",
    "wpTheme",
    "wpPlugins",
    "wpIssues",
    "sslEnabled",
    "directoryIndexing",
    "wpCronFound",
    "userEnumeration",
    "xmlRpcEnabled",
    "hostingProvider",
  ];
  fields.forEach((field) => {
    updateField(field, "Checking...", "text-yellow-500");
  });
}

function updateTableContent(data) {
  for (const [key, value] of Object.entries(data)) {
    switch (key) {
      case "is_wordpress":
        updateField(
          "isWordPress",
          value ? "Yes" : "No",
          value ? "text-green-500" : "text-red-500"
        );
        break;
      case "version":
        updateField(
          "wpVersion",
          value || "Unknown (hidden)",
          value ? "text-red-500" : "text-green-500"
        );
        break;
      case "theme":
        updateField(
          "wpTheme",
          value || "Unknown",
          value ? "text-green-500" : "text-yellow-500"
        );
        break;
      case "plugins":
        updatePlugins(value);
        break;
      case "ssl_enabled":
        updateField(
          "sslEnabled",
          value ? "Yes" : "No",
          value ? "text-green-500" : "text-red-500"
        );
        break;
      case "directory_indexing":
        updateField(
          "directoryIndexing",
          value ? "Enabled (Vulnerable)" : "Disabled",
          value ? "text-red-500" : "text-green-500"
        );
        break;
      case "wp_cron_found":
        updateField(
          "wpCronFound",
          value ? "Found (Potential DoS risk)" : "Not found or disabled",
          value ? "text-red-500" : "text-green-500"
        );
        break;
      case "user_enumeration":
        updateField(
          "userEnumeration",
          value ? "Possible" : "Not detected",
          value ? "text-red-500" : "text-green-500"
        );
        break;
      case "xml_rpc_enabled":
        updateField(
          "xmlRpcEnabled",
          value ? "Enabled" : "Disabled",
          value ? "text-yellow-500" : "text-green-500"
        );
        break;
      case "hosting_provider":
        updateHostingProvider(value);
        break;
      case "issues":
        updateIssues(value);
        break;
    }
  }
}

function updateField(id, value, colorClass) {
  const element = document.getElementById(id);
  if (element) {
    element.textContent = value;
    element.className = `border border-gray-300 p-2 ${colorClass}`;
  }
}

function updatePlugins(plugins) {
  const wpPlugins = document.getElementById("wpPlugins");
  const wpPluginsInfo = document.getElementById("wpPluginsInfo");
  if (Array.isArray(plugins) && plugins.length > 0) {
    wpPlugins.innerHTML = plugins
      .map((plugin) => {
        const [name, version] = plugin.split("|");
        return `<div>${name}: <span class="font-semibold">${
          version || "Unknown version"
        }</span></div>`;
      })
      .join("");
    wpPlugins.className = "border border-gray-300 p-2 text-green-500";
    wpPluginsInfo.textContent = `${plugins.length} plugin(s) detected. Keep these updated to maintain security.`;
  } else {
    wpPlugins.textContent = "None detected";
    wpPlugins.className = "border border-gray-300 p-2 text-yellow-500";
    wpPluginsInfo.textContent =
      "No plugins found. This is unusual for a WordPress site.";
  }
}

function updateHostingProvider(provider) {
  const hostingProvider = document.getElementById("hostingProvider");
  const hostingProviderInfo = document.getElementById("hostingProviderInfo");
  if (provider && provider.name) {
    hostingProvider.textContent = provider.name;
    hostingProvider.className = "border border-gray-300 p-2 text-blue-500";
    if (provider.url) {
      hostingProviderInfo.innerHTML = `Hosting provider identified. <a href="${provider.url}" target="_blank" class="text-blue-500 hover:underline">Visit provider website</a> for more information on their security features.`;
    } else {
      hostingProviderInfo.textContent =
        "Hosting provider identified. Research their security practices for more information.";
    }
  } else {
    hostingProvider.textContent = "Unknown";
    hostingProvider.className = "border border-gray-300 p-2 text-yellow-500";
    hostingProviderInfo.textContent =
      "Could not determine hosting provider. This doesn't affect security directly but can be useful information.";
  }
}

function updateIssues(issues) {
  const wpIssues = document.getElementById("wpIssues");
  const wpIssuesInfo = document.getElementById("wpIssuesInfo");
  if (issues && issues.length > 0) {
    wpIssues.innerHTML = issues
      .map((issue) => `<div class="text-red-500">${issue}</div>`)
      .join("");
    wpIssuesInfo.textContent = `${issues.length} potential security issue(s) found. Address these to improve your site's security.`;
  } else {
    wpIssues.innerHTML = '<div class="text-green-500">No issues detected</div>';
    wpIssuesInfo.textContent =
      "No immediate security issues detected. Continue to monitor and update regularly.";
  }
  wpIssues.className = "border border-gray-300 p-2";
}
