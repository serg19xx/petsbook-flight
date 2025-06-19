// Store module for i18n translations
export default {
  state: {
    translationProgress: 0,
    translationStatus: 'idle', // idle, processing, completed, failed
    translationMessage: '',
    eventSource: null
  },
  
  mutations: {
    setTranslationProgress(state, progress) {
      state.translationProgress = progress
    },
    
    setTranslationStatus(state, status) {
      state.translationStatus = status
    },
    
    setTranslationMessage(state, message) {
      state.translationMessage = message
    },
    
    setEventSource(state, eventSource) {
      state.eventSource = eventSource
    },
    
    resetTranslationState(state) {
      state.translationProgress = 0
      state.translationStatus = 'idle'
      state.translationMessage = ''
      if (state.eventSource) {
        state.eventSource.close()
        state.eventSource = null
      }
    }
  },
  
  actions: {
    startTranslation({ commit, state }, locale) {
      // Сбрасываем предыдущее состояние
      commit('resetTranslationState')
      
      // Устанавливаем статус обработки
      commit('setTranslationStatus', 'processing')
      commit('setTranslationMessage', 'Starting translation...')
      
      // Создаем EventSource
      const eventSource = new EventSource(`/api/i18n/translate-language/${locale}`)
      commit('setEventSource', eventSource)
      
      // Обрабатываем события
      eventSource.addEventListener('start', (event) => {
        const data = JSON.parse(event.data)
        commit('setTranslationMessage', data.message || 'Translation in progress...')
      })
      
      eventSource.addEventListener('complete', (event) => {
        const data = JSON.parse(event.data)
        commit('setTranslationStatus', 'completed')
        commit('setTranslationMessage', data.message || 'Translation completed!')
        
        // Закрываем соединение
        eventSource.close()
        commit('setEventSource', null)
        
        // Через 2 секунды сбрасываем состояние
        setTimeout(() => {
          commit('resetTranslationState')
        }, 2000)
      })
      
      eventSource.addEventListener('error', (event) => {
        const data = JSON.parse(event.data)
        commit('setTranslationStatus', 'failed')
        commit('setTranslationMessage', data.error || 'Translation failed!')
        
        // Закрываем соединение
        eventSource.close()
        commit('setEventSource', null)
        
        // Через 3 секунды сбрасываем состояние
        setTimeout(() => {
          commit('resetTranslationState')
        }, 3000)
      })
      
      eventSource.onerror = () => {
        commit('setTranslationStatus', 'failed')
        commit('setTranslationMessage', 'Connection error!')
        
        // Закрываем соединение
        eventSource.close()
        commit('setEventSource', null)
        
        // Через 3 секунды сбрасываем состояние
        setTimeout(() => {
          commit('resetTranslationState')
        }, 3000)
      }
    },
    
    stopTranslation({ commit, state }) {
      if (state.eventSource) {
        state.eventSource.close()
        commit('setEventSource', null)
      }
      commit('resetTranslationState')
    }
  }
} 