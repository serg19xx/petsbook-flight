<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50">
    <div class="w-full max-w-md rounded-lg bg-white p-8 shadow-md">
      <div v-if="loading" class="text-center">
        <div class="mb-4 text-xl font-semibold text-gray-700">
          Проверка email...
        </div>
      </div>

      <div v-else-if="success" class="text-center">
        <div class="mb-4 text-xl font-semibold text-green-600">
          Email успешно подтвержден!
        </div>
        <p class="mb-4 text-gray-600">
          Теперь вы можете войти в свой аккаунт.
        </p>
        <router-link to="/login" class="inline-block rounded bg-blue-600 px-6 py-2 text-white hover:bg-blue-700">
          Войти
        </router-link>
      </div>

      <div v-else class="text-center">
        <div class="mb-4 text-xl font-semibold text-red-600">
          {{ errorMessage }}
        </div>
        <p class="mb-4 text-gray-600">
          Возможно, ссылка устарела или недействительна.
        </p>
        <router-link to="/login" class="inline-block rounded bg-blue-600 px-6 py-2 text-white hover:bg-blue-700">
          Вернуться на страницу входа
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const loading = ref(true);
const success = ref(false);
const errorMessage = ref('Ошибка при подтверждении email');

onMounted(async () => {
  try {
    const token = route.params.token;
    const response = await axios.get(`${import.meta.env.VITE_API_URL}/api/auth/verify-email/${token}`);

    if (response.data.status === 'success') {
      success.value = true;
    } else {
      errorMessage.value = 'Не удалось подтвердить email';
    }
  } catch (error) {
    errorMessage.value = error.response?.data?.message || 'Ошибка при подтверждении email';
  } finally {
    loading.value = false;
  }
});
</script>