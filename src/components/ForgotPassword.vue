<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
      <h2 class="text-2xl font-bold text-center mb-6">Восстановление пароля</h2>
      
      <form @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Email</label>
          <input 
            type="email" 
            v-model="email" 
            required
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
          >
        </div>

        <button 
          type="submit"
          :disabled="loading"
          class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
        >
          <span v-if="loading">Отправка...</span>
          <span v-else>Отправить инструкции</span>
        </button>
      </form>

      <div v-if="message" :class="['mt-4 p-4 rounded-md', success ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700']">
        {{ message }}
      </div>

      <div class="mt-4 text-center">
        <router-link to="/login" class="text-sm text-primary hover:text-primary-dark">
          Вернуться на страницу входа
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const email = ref('');
const loading = ref(false);
const message = ref('');
const success = ref(false);

const handleSubmit = async () => {
  loading.value = true;
  message.value = '';
  success.value = false;

  try {
    const response = await axios.post(`${import.meta.env.VITE_API_URL}/api/auth/password-reset`, {
      email: email.value
    });

    success.value = true;
    message.value = response.data.message;
    email.value = ''; // Очищаем поле
  } catch (error) {
    success.value = false;
    message.value = error.response?.data?.message || 'Произошла ошибка при отправке запроса';
  } finally {
    loading.value = false;
  }
};
</script>