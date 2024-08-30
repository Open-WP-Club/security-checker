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
  resultsTable.classList.add("hidden");

  try {
    console.log("Sending request to wp_checker.php");
    const response = await fetch("wp_checker.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `url=${encodeURIComponent(url)}`,
    });

    console.log("Response received");
    const data = await response.json();
    console.log("PHP response:", data);

    updateTableContent(data);
  } catch (error) {
    console.error("Error:", error);
    document.getElementById("wpIssues").innerHTML = `Error: ${error.message}`;
    document.getElementById("wpIssues").classList.add("text-red-500");
  } finally {
    loadingDiv.classList.add("hidden");
    resultsTable.classList.remove("hidden");
  }
}

function updateTableContent(data) {
  const isWordPress = document.getElementById("isWordPress");
  const wpVersion = document.getElementById("wpVersion");
  const wpTheme = document.getElementById("wpTheme");
  const wpPlugins = document.getElementById("wpPlugins");
  const wpIssues = document.getElementById("wpIssues");

  isWordPress.textContent = data.is_wordpress ? "Yes" : "No";
  isWordPress.classList.add(
    data.is_wordpress ? "text-green-500" : "text-red-500"
  );

  wpVersion.textContent = data.version || "Unknown (hidden)";
  wpVersion.classList.add(data.version ? "text-green-500" : "text-yellow-500");

  wpTheme.textContent = data.theme || "Unknown";
  wpTheme.classList.add(data.theme ? "text-green-500" : "text-yellow-500");

  if (Array.isArray(data.plugins) && data.plugins.length > 0) {
    wpPlugins.innerHTML = data.plugins
      .map((plugin) => {
        const [name, version] = plugin.split("|");
        return `<div>${name}: <span class="font-semibold">${
          version || "Unknown version"
        }</span></div>`;
      })
      .join("");
    wpPlugins.classList.add("text-green-500");
  } else {
    wpPlugins.textContent = "None detected";
    wpPlugins.classList.add("text-yellow-500");
  }

  if (data.issues && data.issues.length > 0) {
    wpIssues.innerHTML = data.issues
      .map((issue) => `<div class="text-red-500">${issue}</div>`)
      .join("");
  } else {
    wpIssues.innerHTML = '<div class="text-green-500">No issues detected</div>';
  }
}
