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
  const resultsDiv = document.getElementById("results");
  const resultsList = document.getElementById("resultsList");

  if (!loadingDiv || !resultsDiv || !resultsList) {
    console.error("One or more required elements not found");
    return;
  }

  loadingDiv.classList.remove("hidden");
  resultsDiv.classList.add("hidden");
  resultsList.innerHTML = "";

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

    if (data.is_wordpress) {
      resultsList.innerHTML += `<li>Is WordPress: Yes</li>`;
      resultsList.innerHTML += `<li>WordPress Version: ${
        data.version || "Unknown (hidden)"
      }</li>`;
      resultsList.innerHTML += `<li>Theme: ${data.theme || "Unknown"}</li>`;

      if (Array.isArray(data.plugins) && data.plugins.length > 0) {
        resultsList.innerHTML += `<li>Plugins: ${data.plugins.join(", ")}</li>`;
      } else {
        resultsList.innerHTML += "<li>Plugins: None detected</li>";
      }

      if (data.issues && data.issues.length > 0) {
        resultsList.innerHTML += "<li>Issues:";
        resultsList.innerHTML +=
          "<ul>" +
          data.issues.map((issue) => `<li>${issue}</li>`).join("") +
          "</ul></li>";
      } else {
        resultsList.innerHTML += "<li>No issues detected.</li>";
      }
    } else {
      resultsList.innerHTML =
        "<li>This does not appear to be a WordPress site.</li>";
    }
  } catch (error) {
    console.error("Error:", error);
    resultsList.innerHTML = `<li>Error: ${error.message}</li>`;
  } finally {
    loadingDiv.classList.add("hidden");
    resultsDiv.classList.remove("hidden");
  }
}
