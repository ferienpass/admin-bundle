'use strict';
import { Controller } from '@hotwired/stimulus';
import { useClickOutside } from 'stimulus-use';
// @ts-ignore
import { enter, leave } from "el-transition";
export default class default_1 extends Controller {
    connect() {
        useClickOutside(this);
    }
    clickOutside(event) {
        event.preventDefault();
        this.close();
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
default_1.targets = ["dropdown"];
