'use strict';

import {Controller} from '@hotwired/stimulus';
import { useClickOutside } from 'stimulus-use'
// @ts-ignore
import {enter, leave} from "el-transition";

export default class extends Controller {
    static targets = ["dropdown"];

    declare readonly dropdownTarget: HTMLElement;

    connect() {
        useClickOutside(this)
    }

    clickOutside(event: Event) {
        this.close()
    }

    open() {
        this.dropdownTarget.classList.remove("hidden");
        enter(this.dropdownTarget);
    }

    close() {
        Promise.all([
            leave(this.dropdownTarget),
        ]).then(() => {
            this.dropdownTarget.classList.add("hidden");
        });
    }

    toggle() {
        this.dropdownTarget.classList.contains('hidden') ? this.open() : this.close();
    }
}
