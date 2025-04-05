<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8">
      <h2 class="text-2xl font-bold text-center mb-6">Создание нового пароля</h2>
      
      <form v-if="!success" @submit.prevent="handleSubmit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Новый пароль</label>
          <input 
            type="password" 
            v-model="password"
            required
            minlength="8"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
          >
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Подтверждение пароля</label>
          <input 
            type="password" 
            v-model="passwordConfirm"
            required
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
          >
        </div>

        <button 
          type="submit"
          :disabled="loading || !isValid"
          class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary"
        >
          <span v-if="loading">Сохранение...</span>
          <span v-else>Сохранить новый пароль</span>
        </button>
      </form>

      <div v-else class="text-center">
        <div class="mb-4 text-green-600">
          <svg class="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
          </svg>
        </div>
        <h3 class="text-xl font-medium text-gray-900 mb-2">Пароль успешно изменен</h3>
        <p class="text-gray-600 mb-4">Теперь вы можете войти в систему, используя новый пароль.</p>
        <router-link 
          to="/login"
          class="inline-block px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark"
        >
          Перейти к входу
        </router-link>
      </div>

      <div v-if="error" class="mt-4 p-4 bg-red-50 text-red-700 rounded-md">
        {{ error }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const router = useRouter();

const password = ref('');
const passwordConfirm = ref('');
const loading = ref(false);
const error = ref('');
const success = ref(false);

const isValid = computed(() => {
  return password.value.length >= 8 && password.value === passwordConfirm.value;
});

const handleSubmit = async () => {
  if (!isValid.value) {
    error.value = 'Пароли не совпадают или слишком короткие';
    return;
  }

  loading.value = true;
  error.value = '';

  try {
    const response = await axios.post(`${import.meta.env.VITE_API_URL}/api/auth/set-new-password`, {
      token: route.params.token,
      password: password.value
    });

    success.value = true;
  } catch (e) {
    error.value = e.response?.data?.message || 'Произошла ошибка при смене пароля';
  } finally {
    loading.value = false;
  }
};
</script>