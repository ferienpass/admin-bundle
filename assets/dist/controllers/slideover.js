'use strict';
import { Controller } from '@hotwired/stimulus';
// @ts-ignore
import { enter, leave } from "el-transition";
//import {Component, getComponent} from '@symfony/ux-live-component';
import { useClickOutside } from 'stimulus-use';
export default class default_1 extends Controller {
    connect() {
        useClickOutside(this);
    }
    clickOutside(event) {
        this.hide();
    }
    show() {
        this.element.classList.remove("hidden");
        enter(this.panelTarget);
    }
    hide() {
        Promise.all([
            leave(this.panelTarget),
        ]).then(() => {
            this.element.classList.add("hidden");
        });
    }
}
default_1.values = {};
default_1.targets = ["panel"];
