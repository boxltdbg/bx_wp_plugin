document.addEventListener("DOMContentLoaded", function () {
  const apiUrlInput = document.querySelector('input[name="boxnow_api_url"]');
  const productionRadio = document.getElementById("boxnow_env_production");
  const stageRadio = document.getElementById("boxnow_env_stage");

  // Заключваме полето за ръчна редакция
  if (apiUrlInput) {
    apiUrlInput.setAttribute("readonly", "readonly");
  }

  function applyEnvironmentUrl() {
    if (!apiUrlInput) return;
    const env = stageRadio && stageRadio.checked ? "stage" : "production";
    const url = env === "stage" ? "api-stage.boxnow.bg" : "api-production.boxnow.bg";
    apiUrlInput.value = url;
  }

  if (productionRadio) productionRadio.addEventListener("change", applyEnvironmentUrl);
  if (stageRadio) stageRadio.addEventListener("change", applyEnvironmentUrl);

  // Инициално задаване според избраната среда
  applyEnvironmentUrl();

  const emailOption = document.getElementById("send_voucher_email");
  const buttonOption = document.getElementById("display_voucher_button");
  const emailInputContainer = document.getElementById("email_input_container");

  function toggleEmailInput() {
    if (emailOption.checked) {
      emailInputContainer.style.display = "block";
    } else {
      emailInputContainer.style.display = "none";
    }
  }

  emailOption.addEventListener("change", toggleEmailInput);
  buttonOption.addEventListener("change", toggleEmailInput);
});
