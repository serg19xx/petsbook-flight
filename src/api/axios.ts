/// <reference types="vite/client" />

import axios from 'axios';

// Создаем экземпляр axios с базовой конфигурацией
const defaultConfig = {
  baseURL: import.meta.env.VITE_API_URL,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true, // Для работы с куками, если требуется
} as const;

const api = axios.create(defaultConfig);

// Интерцептор для добавления токена авторизации
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Интерцептор для обработки ответов
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      // Очищаем токен при ошибке авторизации
      localStorage.removeItem('token');
      // Можно добавить редирект на страницу логина
      // router.push('/login');
    }
    return Promise.reject(error);
  }
);

export default api; 