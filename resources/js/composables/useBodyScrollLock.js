import { watch, onUnmounted } from 'vue';

/**
 * Trava o scroll do <body> enquanto `isOpen` for true.
 *
 * Por que é necessário:
 *   • Modais com Teleport apenas sobrepõem visualmente o conteúdo, mas o
 *     <body> permanece scrollável. No mobile, o gesto de rolar atinge o
 *     body e dispara pull-to-refresh do navegador (parece "recarregar
 *     a página"). Bug reportado pelo PO em 2026-04-28 nos wizards de
 *     ações rápidas do rebanho.
 *   • `overscroll-behavior: contain` complementa: impede o scroll-chaining
 *     quando o conteúdo interno do modal chega no fim — evita o "salto"
 *     pra fora do modal.
 *
 * Reentrância: usa contador global pra suportar modais empilhados (ex.: um
 * Confirm aberto sobre um wizard). Só restaura o body quando TODOS os
 * locks forem liberados.
 *
 * Uso:
 *   const open = ref(false);
 *   useBodyScrollLock(open);
 *
 * O composable observa o ref/computed e aplica/remove o lock automaticamente.
 * Garante cleanup no onUnmounted (modal fechado por unmount sem toggle).
 */

let lockCount = 0;
let prevOverflow = '';
let prevOverscroll = '';

function lock() {
    if (lockCount === 0 && typeof document !== 'undefined') {
        prevOverflow = document.body.style.overflow;
        prevOverscroll = document.body.style.overscrollBehavior;
        document.body.style.overflow = 'hidden';
        document.body.style.overscrollBehavior = 'contain';
    }
    lockCount++;
}

function unlock() {
    if (lockCount === 0) return;
    lockCount--;
    if (lockCount === 0 && typeof document !== 'undefined') {
        document.body.style.overflow = prevOverflow;
        document.body.style.overscrollBehavior = prevOverscroll;
    }
}

export function useBodyScrollLock(isOpen) {
    let active = false;

    watch(
        isOpen,
        (open) => {
            if (open && !active) {
                lock();
                active = true;
            } else if (!open && active) {
                unlock();
                active = false;
            }
        },
        { immediate: true }
    );

    onUnmounted(() => {
        if (active) {
            unlock();
            active = false;
        }
    });
}
