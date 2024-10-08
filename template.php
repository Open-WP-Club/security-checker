<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>WordPress Site Checker</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="wordpress-checker.js"></script>
</head>

<body class="bg-gray-100 p-8">
  <div class="w-full md:w-2/3 px-3 mx-auto">
    <div class="bg-white p-8 rounded-lg shadow-md">
      <h1 class="text-2xl font-bold mb-4 text-center">WordPress Site Checker</h1>

      <form id="urlForm" class="mb-4">
        <input type="url" id="urlInput" placeholder="Enter website URL" required class="w-full p-2 border rounded mb-2">
        <button type="submit" class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600">
          Check Site
        </button>
      </form>

      <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert"></div>

      <div id="loading" class="hidden text-center mb-4">
        Analyzing site... This may take a few moments.
      </div>

      <table id="resultsTable" class="w-full border-collapse border border-gray-300">
        <thead>
          <tr class="bg-gray-100">
            <th class="border border-gray-300 p-2 w-1/4">Attribute</th>
            <th class="border border-gray-300 p-2 w-1/4">Value</th>
            <th class="border border-gray-300 p-2 w-1/2">Information</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($checks as $id => $name): ?>
            <tr>
              <td class="border border-gray-300 p-2"><?php echo htmlspecialchars($name); ?></td>
              <td id="<?php echo $id; ?>" class="border border-gray-300 p-2">Not checked</td>
              <td id="<?php echo $id; ?>Info" class="border border-gray-300 p-2 text-sm"><?php echo htmlspecialchars($checkInfo[$id]); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>

</html>